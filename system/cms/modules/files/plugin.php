<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Files Plugin
 *
 * Create a list of files
 *
 * @package		PyroCMS
 * @author		Marcos Coelho - PyroCMS Dev Team
 * @copyright	Copyright (c) 2008 - 2011, PyroCMS
 *
 */
class Plugin_Files extends Plugin
{
	private $_files = array();

	public function __construct()
	{
		$this->load->model(array(
			'file_m',
			'file_folders_m'
		));
	}

	/**
	 * Files listing
	 *
	 * Creates a list of files
	 *
	 * Usage:
	 *
	 * {{ files:listing folder="home-slider" type="i" fetch="subfolder|root" }}
	 * 	// your html logic
	 * {{ /files:listing }}
	 *
	 * The tags that are available to use from this method are listed below
	 *
	 * {id}
	 * {folder_id}
	 * {user_id}
	 * {type}
	 * {name}
	 * {filename}
	 * {description}
	 * {extension}
	 * {mimetype}
	 * {width}
	 * {height}
	 * {filesize}
	 * {date_added}
	 *
	 * @return	array
	 */
	public function listing()
	{
		if ( ! $this->content())
		{
			return '';
		}

		$folder_id	= $this->attribute('folder', ''); // Id or Path
		$limit		= $this->attribute('limit', '10');
		$offset		= $this->attribute('offset', '');
		$type		= $this->attribute('type', '');
		$fetch		= $this->attribute('fetch');

		if ( ! empty($folder_id) && (empty($type) || in_array($type, array('a','v','d','i','o'))))
		{
			if (is_numeric($folder_id))
			{
				$folder = $this->file_folders_m->get($folder_id);
			}
			elseif (is_string($folder_id))
			{
				$folder = $this->file_folders_m->get_by_path($folder_id);
			}
		}

		if (empty($folder))
		{
			return array();
		}

		if (in_array($fetch, array('root', 'subfolder')) &&
			$subfolders = $this->file_folders_m->folder_tree(
				$fetch === 'root' ? $folder->root_id : $folder->id
			))
		{
			$ids = array_merge(array((int) $folder->id), array_keys($subfolders));
			$this->file_m->where_in('folder_id', $ids);
		}
		else
		{
			$this->file_m->where('folder_id', $folder->id);
		}

		$type AND $this->file_m->where('type', $type);
		$limit AND $this->file_m->limit($limit);
		$offset AND $this->file_m->limit($offset);

		$files = $this->file_m->get_all();
		$files AND array_merge($this->_files, assoc_array_prop($files));

		return $files;
	}

	public function file($return = '', $type = '')
	{
		// nothing to do
		if ($return && ! in_array($return, array('url', 'path')))
		{
			return '';
		}

		// prepare file params
		$id		= $this->attribute('id');
		$type	= $type && in_array($type, array('a','v','d','i','o')) ? $type : '';

		// get file
		if (isset($this->_files[$id]))
		{
			$file = $this->_files[$id];
		}
		else
		{
			$type AND $this->file_m->where('type', $type);

			$file = $this->file_m->get($id);
		}

		// file not found
		if ( ! $file OR ($type && $file->type !== $type))
		{
			return '';
		}
		// return file fields array
		elseif ( ! $return && $this->content())
		{
			return (array) $file;
		}

		// make uri
		if ($type === 'i')
		{
			if ($size = $this->attribute('size', ''))
			{
				strpos($size, 'x') === FALSE AND $size .= 'x';

				list($width, $height) = explode('/', strtr($size, 'x', '/'));
			}
			else
			{
				$width	= $this->attribute('width', '');
				$height	= $this->attribute('height', '');
			}

			is_numeric($width) OR $width = 'auto';
			is_numeric($height) OR $height = 'auto';

			if ($width === 'auto' && $height === 'auto')
			{
				$dimension = '';
			}
			else
			{
				$mode = $this->attribute('mode', '');
				$mode = in_array($mode, array('fill', 'fit')) ? $mode : '';

				$dimension = trim($width . '/' . $height . '/' . $mode, '/');
			}

			$uri = $dimension ? sprintf('files/thumb/%s/%s', $file->id, $dimension) : sprintf('files/large/%s', $file->id);
		}
		else
		{
			$uri = 'files/download/' . $file->id;
		}

		// return string
		if ($return)
		{
			return $return === 'url' ? site_url($uri) : BASE_URI . $uri;
		}

		$attributes	= $this->attributes();

		foreach (array('base', 'size', 'id', 'title', 'type', 'mode', 'width', 'height') as $key)
		{
			if (isset($attributes[$key]) && ($type !== 'i' OR ! in_array($key, array('width', 'height'))))
			{
				unset($attributes[$key]);
			}

			if (isset($attributes['tag-' . $key]))
			{
				$attributes[$key] = $attributes['tag-' . $key];

				unset($attributes['tag-' . $key]);
			}
		}

		$base = $this->attribute('base', 'url');

		// return an image tag html
		if ($type === 'i')
		{
			$this->load->helper('html');

			if (strpos($size, 'x') !== FALSE && ! isset($attributes['width'], $attributes['height']))
			{
				list($attributes['width'], $attributes['height']) = explode('x', $size);
			}

			return $this->{'_build_tag_location_' . $base}($type, $uri, array(
				'attributes' => $attributes,
				'index_page' => TRUE
			));
		}

		// return an file anchor tag html
		$title = $this->attribute('title');

		return $this->{'_build_tag_location_' . $base}($type, $uri, compact('title', 'attributes'));
	}

	public function image()
	{
		return $this->file('', 'i');
	}

	public function image_url()
	{
		return $this->file_url('i');
	}

	public function image_path()
	{
		return $this->file_path('i');
	}

	public function file_url($type = '')
	{
		return $this->file('url', $type);
	}

	public function file_path($type = '')
	{
		return $this->file('path', $type);
	}

	public function exists()
	{
		$id = $this->attribute('id');

		$exists = (bool) (isset($this->_files[$id]) ? TRUE : $this->file_m->exists($id));

		return $exists && $this->content() ? $this->content() : $exists;
	}
	
	public function folder_exists()
	{
		return $this->file_folders_m->exists($this->attribute('slug'));
	}

	private function _build_tag_location_url($type = '', $uri = '', $extras = array())
	{
		extract($extras);

		if ($type === 'i')
		{
			$attributes['src'] = $uri;

			return img($attributes, $index_page);
		}

		return anchor($uri, $title, $attributes);
	}

	private function _build_tag_location_path($type = '', $uri = '', $extras = array())
	{
		extract($extras);

		// unset config base_url
		$base_url = $this->config->item('base_url');
		$this->config->set_item('base_url', '');

		// generate tag
		if ($type === 'i')
		{
			$attributes['src'] = $uri;

			$tag = img($attributes, $index_page);
		}
		else
		{
			$tag = anchor($uri, $title, $attributes);
		}

		// set config base_url
		$base_url = $this->config->set_item('base_url', $base_url);

		return $tag;
	}
}

/* End of file plugin.php */