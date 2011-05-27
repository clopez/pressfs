#!/usr/bin/env python
#
# pressfs - The WordPress filesystem
# Joseph Scott
# http://josephscott.org/
#

import base64
import calendar
import ConfigParser
import errno
import fuse
import httplib2
import os
import re
import simplejson
import stat
import sys
import time

fuse.fuse_python_api = ( 0, 2 )

class PressFS_Stat( fuse.Stat ) :
	def __init__( self ) :
		self.st_ino		= 0
		self.st_dev		= 0
		self.st_uid		= 0
		self.st_gid		= 0
		self.st_size	= 0
		self.st_atime	= 0
		self.st_mtime	= 0
		self.st_ctime	= 0

		# default to read only file
		self.st_mode = stat.S_IFREG | 0400
		self.st_nlink = 1

	def dir( self, mode = 0400 ) :
		self.st_mode = stat.S_IFDIR | mode
		self.st_nlink = 2

	def size( self, size ) :
		self.st_size = size

	def time( self, when ) :
		self.st_atime = when
		self.st_mtime = when
		self.st_ctime = when

class PressFS( fuse.Fuse ) :
	def __init__( self, *args, **kw ) :
		fuse.Fuse.__init__( self, *args, **kw )

		self.version = '0.1.0'

		if ( os.path.isfile( 'config.ini' ) == False ) :
			print "You need setup config.ini first."
			sys.exit()

		self.config = ConfigParser.ConfigParser()
		self.config.read( 'config.ini' )

		self.wp_url = self.config.get( 'WordPress', 'url' ) + '?pressfs=1'
		self.wp_username = self.config.get( 'WordPress', 'username' )
		self.wp_password = self.config.get( 'WordPress', 'password' )
		self.req_expire = self.config.getint( 'Cache', 'req_expire' )

		self.req_cache = { }

	def getattr( self, path ) :
		st = PressFS_Stat()

		if ( path == '/' ) :
			st.dir()
			return st

		if ( path == '/users' ) :
			st.dir()
			return st

		match = re.match( '/users/(.*?)/(.*)', path )
		if ( match ) :
			users = self.wp_request( 'get_user_list' )['users']
			user = users[match.group(1)]
			when = time.strptime( user['registered'], '%Y-%m-%d %H:%M:%S' )

			st.size( len( user[match.group(2)] ) )
			st.time( time.mktime( when ) )
			return st

		match = re.match( '/users/(.*)', path )
		if ( match ) :
			users = self.wp_request( 'get_user_list' )['users']
			user = users[match.group(1)]
			when = time.strptime( user['registered'], '%Y-%m-%d %H:%M:%S' )

			st.dir()
			st.time( time.mktime( when ) )
			return st

		return -errno.ENOENT

	def read( self, path, size, offset ) :
		data = ''

		match = re.match( '/users/(.*?)/(.*)', path )
		if ( match ) :
			users = self.wp_request( 'get_user_list' )['users']
			user = users[ match.group( 1 ) ]
			data = user[ match.group( 2 ) ]

		return self.read_data( data, size, offset )

	def read_data( self, data, size, offset ) :
		slen = len( data )
		if ( offset < slen ) :
			if ( ( offset + size ) > slen ) :
				size = slen - offset
			buf = data[ offset : offset + size ]
		else :
			buf = ''

		return buf

	def readdir( self, path, offset ) :
		yield fuse.Direntry( '.' )
		yield fuse.Direntry( '..' )

		if ( path == '/' ) :
			yield fuse.Direntry( 'users' )
			return

		if ( path == '/users' ) :
			users = self.wp_request( 'get_user_list' )['users']
			for ( u ) in users :
				yield fuse.Direntry( u )
			return

		match = re.match( '/users/(.*)', path )
		if ( match ) :
			users = self.wp_request( 'get_user_list' )['users']
			user = users[ match.group(1) ]
			for ( attr ) in user :
				yield fuse.Direntry( attr )
			return

	def wp_request( self, action ) :
		req_url = self.wp_url + '&call=' + action
		now = calendar.timegm( time.gmtime() )

		# check the cache first
		if ( req_url in self.req_cache ) :
			if ( self.req_cache[req_url]['expire'] > now ) :
				return self.req_cache[req_url]['data']
			else :
				del self.req_cache[req_url]

		# httplib2 won't send auth headers on the first request
		# so we force them in
		req_auth = base64.encodestring(
			self.wp_username + ':' + self.wp_password
		)

		req_headers = {
			'Authorization' : 'Basic ' + req_auth,
			'User-Agent' : 'PressFS/' + self.version
		}

		print ">> WP REQUEST : " + req_url
		http = httplib2.Http()
		resp, content = http.request(
			req_url,
			'POST',
			headers = req_headers
		)

		self.req_cache[req_url] = {
			'data'	: simplejson.loads( content ),
			'expire': now + self.req_expire
		}

		return self.req_cache[req_url]['data']

if ( __name__ == '__main__' ) :
	fs = PressFS()
	fs.parse( errex = 1 )
	fs.main()
