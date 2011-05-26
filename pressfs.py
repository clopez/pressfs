#!/usr/bin/env python
#
# pressfs - The WordPress filesystem
# Joseph Scott
# http://josephscott.org/
#

import ConfigParser
import errno
import fuse
import stat

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

		self.config = ConfigParser.ConfigParser()
		self.config.read( 'config.ini' )

		self.wp_url = self.config.get( 'WordPress', 'url' ) + '?pressfs=1'
		self.wp_username = self.config.get( 'WordPress', 'username' )
		self.wp_password = self.config.get( 'WordPress', 'password' )

	def getattr( self, path ) :
		st = PressFS_Stat()

		if ( path == '/' ) :
			st.dir()
			return st

		if ( path == '/users' ) :
			st.dir()
			return st

		return -errno.ENOENT

	def readdir( self, path, offset ) :
		yield fuse.Direntry( '.' )
		yield fuse.Direntry( '..' )

		if ( path == '/' ) :
			yield fuse.Direntry( 'users' )

if ( __name__ ) == '__main__' :
	fs = PressFS()
	fs.parse( errex = 1 )
	fs.main()
