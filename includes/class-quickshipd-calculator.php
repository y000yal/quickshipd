<?php
/**
 * Delivery date calculation engine.
 *
 * Pure logic class — no WordPress I/O in the core math so it stays testable.
 * WordPress helpers (wp_timezone, date_i18n) are injected at the call site
 * via the static factory method ::from_settings().
 *
 * @package QuickShipD
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Calculator
 *
 * Calculates estimated delivery date ranges from a set of shipping parameters.
 *
 * @since 1.0.0
 */
class QuickShipD_Calculator {

	/**
	 * Minimum business days for delivery.
	 *
	 * @var int
	 */
	private $min_days;

	/**
	 * Maximum business days for delivery.
	 *
	 * @var int
	 */
	private $max_days;

	/**
	 * Daily order cutoff hour (0–23).
	 *
	 * @var int
	 */
	private $cutoff_hour;

	/**
	 * Daily order cutoff minute (0, 15, 30, or 45).
	 *
	 * @var int
	 */
	private $cutoff_min;

	/**
	 * Weekday numbers to skip (0 = Sunday … 6 = Saturday).
	 *
	 * @var int[]
	 */
	private $excluded_days;

	/**
	 * Specific dates to skip.
	 * Supports 'Y-m-d' for a one-off date and 'XXXX-m-d' for yearly recurring.
	 *
	 * @var string[]
	 */
	private $holidays;

	/**
	 * Constructor.
	 *
	 * @param int      $min_days      Minimum business days.
	 * @param int      $max_days      Maximum business days.
	 * @param int      $cutoff_hour   Cutoff hour (0–23).
	 * @param int      $cutoff_min    Cutoff minute (0, 15, 30, 45).
	 * @param int[]    $excluded_days Weekday numbers to exclude.
	 * @param string[] $holidays      Holiday dates.
	 */
	public function __construct(
		int $min_days,
		int $max_days,
		int $cutoff_hour,
		int $cutoff_min,
		array $excluded_days,
		array $holidays
	) {
		$this->min_days      = max( 0, $min_days );
		$this->max_days      = max( $this->min_days, $max_days );
		$this->cutoff_hour   = min( 23, max( 0, $cutoff_hour ) );
		$this->cutoff_min    = $this->sanitize_cutoff_min( $cutoff_min );
		$this->excluded_days = array_map( 'intval', $excluded_days );
		$this->holidays      = array_map( 'strval', $holidays );
	}

	/**
	 * Build a calculator from the plugin's wp_options settings.
	 *
	 * Accepts an optional $overrides array so shipping-method integration can
	 * pass min/max_days without touching global options.
	 *
	 * @param  array    $overrides  Associative array of setting overrides.
	 * @param  int|null $product_id Product ID for per-product meta overrides.
	 * @return self
	 */
	public static function from_settings( array $overrides = array(), ?int $product_id = null ): self {
		$min_days    = (int) get_option( 'quickshipd_min_days', 3 );
		$max_days    = (int) get_option( 'quickshipd_max_days', 5 );
		$cutoff_hour = (int) get_option( 'quickshipd_cutoff_hour', 14 );
		$cutoff_min  = (int) get_option( 'quickshipd_cutoff_min', 0 );

		// Per-product meta overrides.
		if ( $product_id ) {
			$meta_min = get_post_meta( $product_id, '_quickshipd_min_days', true );
			$meta_max = get_post_meta( $product_id, '_quickshipd_max_days', true );
			if ( '' !== $meta_min && is_numeric( $meta_min ) ) {
				$min_days = (int) $meta_min;
			}
			if ( '' !== $meta_max && is_numeric( $meta_max ) ) {
				$max_days = (int) $meta_max;
			}
		}

		// Caller-supplied overrides (e.g. from a shipping method instance).
		if ( isset( $overrides['min_days'] ) && '' !== $overrides['min_days'] ) {
			$min_days = (int) $overrides['min_days'];
		}
		if ( isset( $overrides['max_days'] ) && '' !== $overrides['max_days'] ) {
			$max_days = (int) $overrides['max_days'];
		}

		// Excluded weekdays.
		$exclude_weekends = 'yes' === get_option( 'quickshipd_exclude_weekends', 'yes' );
		$excluded_days    = (array) get_option( 'quickshipd_excluded_days', array() );
		if ( $exclude_weekends ) {
			// Ensure 0 (Sunday) and 6 (Saturday) are in the list.
			$excluded_days = array_unique( array_merge( $excluded_days, array( 0, 6 ) ) );
		}

		// Holidays: one date per line in the textarea option.
		$holidays_raw = get_option( 'quickshipd_holidays', '' );
		$holidays     = self::parse_holidays( $holidays_raw );

		return new self( $min_days, $max_days, $cutoff_hour, $cutoff_min, $excluded_days, $holidays );
	}

	/**
	 * Run the calculation and return a result array.
	 *
	 * @param  \DateTimeInterface|null $now  Inject current time (null = wp_timezone now).
	 * @return array{
	 *     min_date: \DateTime,
	 *     max_date: \DateTime,
	 *     is_range: bool,
	 *     countdown_seconds: int,
	 *     show: bool
	 * }
	 */
	public function calculate( ?\DateTimeInterface $now = null ): array {
		if ( null === $now ) {
			$tz  = wp_timezone();
			$now = new \DateTime( 'now', $tz );
		}

		// Determine the effective start date (today or tomorrow based on cutoff).
		$start = clone $now;
		$start->setTime( 0, 0, 0 );

		$cutoff = clone $now;
		$cutoff->setTime( $this->cutoff_hour, $this->cutoff_min, 0 );

		$past_cutoff = $now >= $cutoff;

		if ( $past_cutoff ) {
			$start->modify( '+1 day' );
		}

		// Countdown seconds: how many seconds until the cutoff (0 if past).
		$countdown_seconds = 0;
		if ( ! $past_cutoff ) {
			$countdown_seconds = (int) ( $cutoff->getTimestamp() - $now->getTimestamp() );
		}

		// Add business days.
		$min_date = $this->add_business_days( clone $start, $this->min_days );
		$max_date = $this->add_business_days( clone $start, $this->max_days );

		$is_range = $min_date->format( 'Y-m-d' ) !== $max_date->format( 'Y-m-d' );

		return array(
			'min_date'          => $min_date,
			'max_date'          => $max_date,
			'is_range'          => $is_range,
			'countdown_seconds' => $countdown_seconds,
			'show'              => true,
		);
	}

	/**
	 * Add a given number of business days to a date, skipping excluded
	 * weekdays and holidays.
	 *
	 * @param  \DateTime $date  Starting date (will be mutated).
	 * @param  int       $days  Number of business days to add.
	 * @return \DateTime
	 */
	private function add_business_days( \DateTime $date, int $days ): \DateTime {
		$added = 0;
		// Safety valve: maximum iterations to prevent infinite loops when all
		// days of the week are excluded or holiday list is massive.
		$max_iterations = $days + 365;

		while ( $added < $days && $max_iterations-- > 0 ) {
			$date->modify( '+1 day' );
			if ( ! $this->is_excluded( $date ) ) {
				++$added;
			}
		}

		// If min_days/max_days is 0, the start day itself is valid only if not
		// excluded — otherwise advance to the next valid day.
		if ( 0 === $days ) {
			$safety = 365;
			while ( $this->is_excluded( $date ) && $safety-- > 0 ) {
				$date->modify( '+1 day' );
			}
		}

		return $date;
	}

	/**
	 * Check whether a given date is excluded (weekend/holiday/excluded weekday).
	 *
	 * @param  \DateTimeInterface $date  Date to check.
	 * @return bool
	 */
	private function is_excluded( \DateTimeInterface $date ): bool {
		$dow = (int) $date->format( 'w' ); // 0 = Sunday, 6 = Saturday.

		if ( in_array( $dow, $this->excluded_days, true ) ) {
			return true;
		}

		return $this->is_holiday( $date );
	}

	/**
	 * Check whether a date matches a holiday entry.
	 *
	 * @param  \DateTimeInterface $date  Date to check.
	 * @return bool
	 */
	private function is_holiday( \DateTimeInterface $date ): bool {
		$ymd     = $date->format( 'Y-m-d' );
		$monthly = 'XXXX-' . $date->format( 'm-d' );

		foreach ( $this->holidays as $h ) {
			$h = trim( $h );
			if ( '' === $h ) {
				continue;
			}
			if ( $h === $ymd || $h === $monthly ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse a newline-separated holidays string into an array of date strings.
	 *
	 * @param  string $raw  Raw textarea value.
	 * @return string[]
	 */
	public static function parse_holidays( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return array();
		}
		return array_filter(
			array_map( 'trim', explode( "\n", $raw ) ),
			static function ( string $line ): bool {
				return '' !== $line && '#' !== $line[0];
			}
		);
	}

	/**
	 * Format a DateTime for display, respecting WP locale.
	 *
	 * @param  \DateTimeInterface $date    Date to format.
	 * @param  string             $format  PHP date format string.
	 * @return string
	 */
	public static function format_date( \DateTimeInterface $date, string $format = 'D, M j' ): string {
		return date_i18n( $format, $date->getTimestamp() );
	}

	/**
	 * Format seconds as "Xh Ym" or "Ym" for countdown display.
	 *
	 * @param  int $seconds  Remaining seconds.
	 * @return string
	 */
	public static function format_countdown( int $seconds, bool $show_seconds = true ): string {
		if ( $seconds <= 0 ) {
			return '';
		}
		$hours   = (int) floor( $seconds / 3600 );
		$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
		$secs    = $seconds % 60;

		if ( $show_seconds ) {
			if ( $hours > 0 ) {
				return sprintf(
					/* translators: 1: hours, 2: minutes, 3: seconds */
					__( '%1$dh %2$dm %3$ds', 'quickshipd' ),
					$hours,
					$minutes,
					$secs
				);
			}
			if ( $minutes > 0 ) {
				return sprintf(
					/* translators: 1: minutes, 2: seconds */
					__( '%1$dm %2$ds', 'quickshipd' ),
					$minutes,
					$secs
				);
			}
			return sprintf(
				/* translators: 1: seconds */
				__( '%1$ds', 'quickshipd' ),
				$secs
			);
		}

		// Seconds hidden — show hours + minutes only.
		if ( $hours > 0 ) {
			return sprintf(
				/* translators: 1: hours, 2: minutes */
				__( '%1$dh %2$dm', 'quickshipd' ),
				$hours,
				$minutes
			);
		}
		return sprintf(
			/* translators: 1: minutes */
			__( '%1$dm', 'quickshipd' ),
			$minutes > 0 ? $minutes : 1
		);
	}

	/**
	 * Ensure the cutoff minute is one of the allowed values.
	 *
	 * @param  int $min  Raw minute value.
	 * @return int
	 */
	private function sanitize_cutoff_min( int $min ): int {
		$allowed = array( 0, 15, 30, 45 );
		if ( in_array( $min, $allowed, true ) ) {
			return $min;
		}
		// Round to nearest allowed value.
		$closest = 0;
		$diff    = PHP_INT_MAX;
		foreach ( $allowed as $v ) {
			if ( abs( $min - $v ) < $diff ) {
				$diff    = abs( $min - $v );
				$closest = $v;
			}
		}
		return $closest;
	}

	// -----------------------------------------------------------------------
	// Getters — used by tests.
	// -----------------------------------------------------------------------

	/** @return int */
	public function get_min_days(): int {
		return $this->min_days;
	}

	/** @return int */
	public function get_max_days(): int {
		return $this->max_days;
	}
}
