
## Quick Start

- install and activate the pressfs WordPress plugin
- copy example-config.ini config.ini
- edit config.ini, set values in WordPress section
- python pressfs.py /your/mount/point/

## Author
Joseph Scott - <http://josephscott.org/>

## License

License is <a href="http://www.opensource.org/licenses/mit-license.php">MIT</a> style.

## ChangeLog

### 0.3.0 - Tue 28 Jun 2011
- Read only exposure of media files managed by WordPress in the top level /media directory
- New pressfs WordPress API methods: get_media_file, get_media_list

### 0.2.1 - Mon 13 Jun 2011
- Fix typo in class-pressfs.php (scribu)

### 0.2.0 - Mon 6 Jun 2011
- Start adding write support
