<?php
/**
 * Central interface for cloud assets module.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.10.2014
 * @package cloudassets
 */
class CloudAssets extends Object
{
	/** @var bool - kill switch via config - if true the module will ignore all cloud buckets */
	private static $disabled = false;

	/** @var bool - kill switch for uploading local changes to the cdn - useful for safeguarding local development environments */
	private static $uploads_disabled = false;

	/** @var array */
	private static $map = array(
		//'assets/folder/path' => array(
		//  'Type'      => 'RackspaceBucket',
		//  'BaseURL'   => 'http://cdnurl.com/',
		//  'SecureURL' => 'https://cdnurl.com/',
		//  'Container' => 'container-name',
		//  'UserID'    => 'username',
		//  'ApiKey'    => 'key',
		//  'LocalCopy' => true,
		//);
	);

	/** @var array - merged in with all bucket configs */
	private static $defaults = array();

	/** @var array - add to this if you have other file subclasses floating around */
	private static $wrappers = array(
		'File'              => 'CloudFile',
		'Image'             => 'CloudImage',
		'CloudImageCached'  => 'CloudImageCached', // this is awkward but prevents it from trying to transform Image_Cached
	);

	/** @var string - placeholder string used for local files */
	private static $file_placeholder = 'CloudFile';

	/** @var string - if an image is missing on the remote (usually when creating a thumbnail) use this instead */
	private static $missing_image = 'cloudassets/images/missing.svg';

	/** @var array - only keep one instance of each bucket */
	protected $bucketCache = array();


	/**
	 * @return CloudAssets
	 */
	public static function inst() {
		return Injector::inst()->get('CloudAssets');
	}


	/**
	 * @param string|File $filename
	 * @return CloudBucket
	 */
	public function map($filename) {
		if (Config::inst()->get('CloudAssets', 'disabled')) return null;
		if (is_object($filename)) $filename = $filename->getFilename();
		$maps = Config::inst()->get('CloudAssets', 'map');

		foreach ($maps as $path => $cfg) {
			if (empty($cfg[ CloudBucket::TYPE ])) continue;
			if (strpos($filename, $path) === 0) {
				if (!isset($this->bucketCache[$path])) {
					// merge in default config if needed
					$defaults = Config::inst()->get('CloudAssets', 'defaults');
					if (!empty($defaults) && is_array($defaults)) $cfg = array_merge($defaults, $cfg);

					// instantiate the bucket
					$this->bucketCache[$path] = Injector::inst()->create($cfg[CloudBucket::TYPE], $path, $cfg);
				}

				return $this->bucketCache[$path];
			}
		}

		return null;
	}


	/**
	 * Updates the cloud status of all files (used in tests and cron job)
	 */
	public function updateAllFiles() {
		foreach (File::get() as $f) {
			$f->updateCloudStatus();
		}
	}


	/**
	 * @param string $className
	 * @return string
	 */
	public function getWrapperClass($className) {
		$wrappers = Config::inst()->get('CloudAssets', 'wrappers');
		// Needs to be wrapped
		if (isset($wrappers[$className])) return $wrappers[$className];
		// Already wrapped
		if (in_array($className, $wrappers, true)) return $className;
		// Can't be wrapped
		return null;
	}


	/**
	 * Wipes out any buckets we've saved
	 */
	public function clearBucketCache() {
		$this->bucketCache = array();
	}
}