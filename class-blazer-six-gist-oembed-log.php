<?php
/**
 * Blazer Six Gist oEmbed
 *
 * @package BlazerSix\GistoEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones <garyy@garyjones.co.uk>
 * @copyright Copyright (c) 2012, Blazer Six, Inc.
 * @license GPL-2.0+
 */

/**
 * Logging class.
 *
 * This class handles the recording of log messages - in particular, messages at
 * the debug level.
 *
 * It follows a subset of PSR-3 and when WordPress bumps to PHP 5.3 as
 * its minimum, then it can implement Psr\Log\LoggerInterface with minimal
 * changes to the interface. Note however, that code in this file doesn't
 * interact directly with WordPress at all.
 *
 * @see https://github.com/php-fig/log
 *
 * @package BlazerSix\GistoEmbed
 * @author Gary Jones <garyy@garyjones.co.uk>
 * @author Brady Vercher <brady@blazersix.com>
 */
class Blazer_Six_Gist_oEmbed_Log {
	/**
	 * Holds the log messages and contextual data.
	 *
	 * The format is something like the following:
	 *
	 * $logs = array(
	 *     'fookey' => array(
	 *         array(
	 *             'message' => 'Some message.',
	 *             'qwe'     => 'rty';
	 *         ),
	 *         array(
	 *             'message' => 'Another message for the same gist shortcode.',
	 *             'extra'     => 'data';
	 *             'if'     => 'needed';
	 *         ),
	 *     ),
	 *     'barkey' => array(
	 *         array(
	 *             'message' => 'Some message for gist bar.',
	 *             'qwe'     => 'rty';
	 *         ),
	 *         array(
	 *             'message' => 'Another message for gist bar.',
	 *             'extra'     => 'data';
	 *             'if'     => 'needed';
	 *         ),
	 *     ),
	 * );
	 */
	protected $logs = array();

	/**
	 * Detailed debug information.
	 *
	 * @since 1.1.0
	 *
	 * @uses Blazer_Six_Gist_oEmbed_Log::log()
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function debug( $message, array $context = array() ) {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log with an arbitrary level.
	 *
	 * Include a 'key' key in the $context argument to group log messages
	 * together. If none is provided, the message is grouped by itself under an
	 * md5() hash of the message itself.
	 *
	 * Note that at this point in time, we don't actually do anything with the
	 * $level argument.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed  $level
	 * @param string $message
	 * @param array  $context
	 */
	public function log( $level, $message, array $context = array() ) {
		$context['message'] = $message;
		$key = isset( $context['key'] ) ? $context['key'] : md5( $message );
		unset( $context['key'] );
		$this->logs[$key][] = $context;
	}

	/**
	 * Return recorded logs data.
	 *
	 * Under PSR-1, this method would be called getLogs().
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function get_logs() {
		return $this->logs;
	}
}