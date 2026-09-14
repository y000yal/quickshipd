<?php
/**
 * Delivery date calculation engine.
 *
 * Pure logic class — no WordPress I/O in the core math so it stays testable.
 * WordPress helpers (wp_timezone, wp_date) are injected at the call site
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

		// Weekdays orders are not dispatched on.
		$excluded_days = (array) get_option( 'quickshipd_excluded_days', array() );

		// Holidays: structured entries (or legacy textarea string — migrated on read).
		$holidays_raw = get_option( 'quickshipd_holidays', array() );
		$holidays     = self::expand_holidays( self::normalize_holiday_entries( $holidays_raw ) );

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

		// Roll forward to the dispatch day: nothing goes out on an excluded day,
		// so the delivery count must start after it, not on it.
		$safety = 365;
		while ( $this->is_excluded( $start ) && $safety-- > 0 ) {
			$start->modify( '+1 day' );
		}

		// Countdown only means anything while today is still the dispatch day.
		$countdown_seconds = 0;
		if ( ! $past_cutoff && $start->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) ) {
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
	 * Kept for legacy textarea values and unit tests.
	 *
	 * @param  string $raw  Raw textarea value.
	 * @return string[]
	 */
	public static function parse_holidays( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array_map( 'trim', explode( "\n", $raw ) ),
				static function ( string $line ): bool {
					return '' !== $line && '#' !== $line[0];
				}
			)
		);
	}

	/**
	 * Normalize stored holidays into structured entries.
	 *
	 * Accepts a legacy textarea string, a list of flat date strings, or an
	 * array of structured entries. Invalid / year-crossing ranges are dropped.
	 *
	 * @param  mixed $raw  Option value.
	 * @return array<int, array{type: string, start: string, end: string, recurring: bool}>
	 */
	public static function normalize_holiday_entries( $raw ): array {
		if ( is_string( $raw ) ) {
			return self::migrate_textarea_holidays( $raw );
		}

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return array();
		}

		// Legacy flat list of Y-m-d / XXXX-m-d strings (no 'type' key).
		$first = reset( $raw );
		if ( is_string( $first ) ) {
			$entries = array();
			foreach ( $raw as $line ) {
				$entry = self::line_to_entry( (string) $line );
				if ( null !== $entry ) {
					$entries[] = $entry;
				}
			}
			return $entries;
		}

		$entries = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$sanitized = self::sanitize_holiday_entry( $item );
			if ( null !== $sanitized ) {
				$entries[] = $sanitized;
			}
		}

		return $entries;
	}

	/**
	 * Expand structured holiday entries into flat day keys for matching.
	 *
	 * @param  array $entries  Structured entries from normalize_holiday_entries().
	 * @return string[]        Keys like '2026-12-25' or 'XXXX-12-25'.
	 */
	public static function expand_holidays( array $entries ): array {
		$keys = array();

		foreach ( $entries as $entry ) {
			$sanitized = self::sanitize_holiday_entry( $entry );
			if ( null === $sanitized ) {
				continue;
			}

			$type      = $sanitized['type'];
			$start     = $sanitized['start'];
			$end       = $sanitized['end'];
			$recurring = $sanitized['recurring'];

			if ( 'single' === $type ) {
				$keys[] = $recurring ? self::to_recurring_key( $start ) : $start;
				continue;
			}

			// Range: walk day-by-day (same calendar year only).
			try {
				$cursor = new \DateTimeImmutable( $start );
				$last   = new \DateTimeImmutable( $end );
			} catch ( \Exception $e ) {
				continue;
			}

			if ( $cursor > $last ) {
				continue;
			}

			$safety = 366;
			while ( $cursor <= $last && $safety-- > 0 ) {
				$ymd    = $cursor->format( 'Y-m-d' );
				$keys[] = $recurring ? self::to_recurring_key( $ymd ) : $ymd;
				$cursor = $cursor->modify( '+1 day' );
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Sanitize a single holiday entry array.
	 *
	 * @param  array $item  Raw entry.
	 * @return array{type: string, start: string, end: string, recurring: bool}|null
	 */
	public static function sanitize_holiday_entry( array $item ): ?array {
		$type = isset( $item['type'] ) ? strtolower( trim( (string) $item['type'] ) ) : 'single';
		if ( ! in_array( $type, array( 'single', 'range' ), true ) ) {
			$type = 'single';
		}

		$start = isset( $item['start'] ) ? self::normalize_ymd( (string) $item['start'] ) : '';
		if ( '' === $start ) {
			return null;
		}

		$recurring = ! empty( $item['recurring'] );
		$end       = '';

		if ( 'range' === $type ) {
			$end = isset( $item['end'] ) ? self::normalize_ymd( (string) $item['end'] ) : '';
			if ( '' === $end || $end < $start ) {
				return null;
			}
			// Same-year only (no year-crossing ranges).
			if ( substr( $start, 0, 4 ) !== substr( $end, 0, 4 ) ) {
				return null;
			}
		}

		return array(
			'type'      => $type,
			'start'     => $start,
			'end'       => $end,
			'recurring' => $recurring,
		);
	}

	/**
	 * Migrate a legacy textarea value into structured entries.
	 *
	 * @param  string $raw  Textarea contents.
	 * @return array<int, array{type: string, start: string, end: string, recurring: bool}>
	 */
	public static function migrate_textarea_holidays( string $raw ): array {
		$entries = array();
		foreach ( self::parse_holidays( $raw ) as $line ) {
			$entry = self::line_to_entry( $line );
			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}
		return $entries;
	}

	/**
	 * Convert a flat date line into a structured single entry.
	 *
	 * @param  string $line  Y-m-d or XXXX-m-d.
	 * @return array{type: string, start: string, end: string, recurring: bool}|null
	 */
	private static function line_to_entry( string $line ): ?array {
		$line = trim( $line );
		if ( '' === $line ) {
			return null;
		}

		$recurring = false;
		if ( 0 === strpos( $line, 'XXXX-' ) ) {
			$recurring = true;
			// Use a leap year so Feb 29 is valid during migration.
			$line = '2024-' . substr( $line, 5 );
		}

		$ymd = self::normalize_ymd( $line );
		if ( '' === $ymd ) {
			return null;
		}

		return array(
			'type'      => 'single',
			'start'     => $ymd,
			'end'       => '',
			'recurring' => $recurring,
		);
	}

	/**
	 * Validate and normalize a Y-m-d date string.
	 *
	 * @param  string $value  Candidate date.
	 * @return string         Normalized Y-m-d or empty string.
	 */
	private static function normalize_ymd( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
			return '';
		}
		return $value;
	}

	/**
	 * Convert a Y-m-d date to a recurring XXXX-m-d key.
	 *
	 * @param  string $ymd  Y-m-d date.
	 * @return string
	 */
	private static function to_recurring_key( string $ymd ): string {
		return 'XXXX-' . substr( $ymd, 5 );
	}

	/**
	 * Format a DateTime for display, respecting WP locale.
	 *
	 * @param  \DateTimeInterface $date    Date to format.
	 * @param  string             $format  PHP date format string.
	 * @return string
	 */
	public static function format_date( \DateTimeInterface $date, string $format = 'D, M j' ): string {
		// wp_date(), not date_i18n(): date_i18n() expects a legacy offset-summed
		// timestamp and shifts a real one back by the site's UTC offset, which
		// rolled midnight dates onto the previous day in UTC+ timezones.
		return wp_date( $format, $date->getTimestamp() );
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
