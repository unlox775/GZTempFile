<?php

/********************
 *
 * GZTempFile - Simple GZ file writer with multi-use filehandle
 *
 * 2015 by David Buchanan - http://joesvolcano.net/
 *
 * GitHub: https://github.com/unlox775/GZTempFile
 *
 * Based on Code snippet on PHP.net docs by David Gero:
 *
 *     http://php.net/manual/en/function.gzopen.php
 *
 ********************/

class GZTempFile {
	private $__fh = null;
	public $uncompressed_bytes = 0;
	public $filesize = null;
	private $gz_filter = null;
	private $file_hash = null;
	private $final_read_fh = false;
	private $__buffer = '';
	private $__buffer_len = 0;

	public function __construct($filename = 'data', $fh = null) {
		$this->__fh = is_null($fh) ? fopen('php://temp','w+') : $fh;
		fwrite($this->__fh, "\x1F\x8B\x08\x08".pack("V", time())."\0\xFF", 10); // GZ file header
		fwrite($this->__fh, str_replace("\0", "", basename($filename)) ."\0");  // GZ filename = data, needed???
		$this->gz_filter = stream_filter_append($this->__fh, "zlib.deflate", STREAM_FILTER_WRITE, -1);
		$this->uncompressed_bytes = 0;
		$this->file_hash = hash_init("crc32b");
	}

	public function fwrite($str,$length = null) {
		if ( $this->final_read_fh ) { throw new Exception("GZTempFile has already been finalized and closed.  No more writing"); }
		hash_update($this->file_hash, $str);
		$this->uncompressed_bytes += strlen($str);
		$this->__buffer_len += strlen($str);
		$this->__buffer .= $str;
		if ( $this->__buffer_len >= 64 * 1024 ) { $this->flushBuffer(); }
	}
	public function flushBuffer() {
		if ( $this->__buffer_len == 0 ) { return false; }
		$return = fwrite($this->__fh, $this->__buffer);
		$this->__buffer_len = 0;
		$this->__buffer = '';
		return $return;
	}

	public function getReadFilehandle() {		
		if ( ! $this->final_read_fh ) {
			$this->flushBuffer();
			stream_filter_remove($this->gz_filter);
			$crc = hash_final($this->file_hash, TRUE);            // hash_final is a string, not an integer
			fwrite($this->__fh, $crc[3].$crc[2].$crc[1].$crc[0]); // need to reverse the hash_final string so it's little endian
		    fwrite($this->__fh, pack("V", $this->uncompressed_bytes), 4);

		    $this->filesize = ftell($this->__fh);
		    rewind($this->__fh);
			$this->final_read_fh = $this->__fh;
		}
		return $this->final_read_fh;
	}
}
