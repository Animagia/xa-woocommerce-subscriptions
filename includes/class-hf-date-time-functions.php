<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Hforce_Date_Time_Utils {

    public function __construct() {
        ;
    }
    public static function subscription_period_strings($number = 1, $period = '') {

        $subscription_periods = apply_filters('hf_subscription_periods', array(
            'day' => sprintf(_nx('day', '%s days', $number, 'Subscription billing period.', 'xa-woocommerce-subscription'), $number),
            'week' => sprintf(_nx('week', '%s weeks', $number, 'Subscription billing period.', 'xa-woocommerce-subscription'), $number),
            'month' => sprintf(_nx('month', '%s months', $number, 'Subscription billing period.', 'xa-woocommerce-subscription'), $number),
            'year' => sprintf(_nx('year', '%s years', $number, 'Subscription billing period.', 'xa-woocommerce-subscription'), $number),
                )
        );

        return (!empty($period) ) ? $subscription_periods[$period] : $subscription_periods;
    }

    public static function get_original_subscription_ranges() {

        
        $available_intervals = array('day', 'week', 'month', 'year');
        foreach ( $available_intervals as $period) {
            
            $subscription_lengths = array( __('Never expire', 'xa-woocommerce-subscription'),);

            switch ($period) {
                case 'day':
                    $subscription_lengths[] = __('1 day', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 90);
                    break;
                case 'week':
                    $subscription_lengths[] = __('1 week', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 52);
                    break;
                case 'month':
                    $subscription_lengths[] = __('1 month', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 24);
                    break;
                case 'year':
                    $subscription_lengths[] = __('1 year', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 5);
                    break;
            }

            foreach ($subscription_range as $number) {
                $subscription_range[$number] = self::subscription_period_strings($number, $period);
            }

            $subscription_lengths += $subscription_range;
            $subscription_ranges[$period] = $subscription_lengths;
        }

        return $subscription_ranges;
    }

    public static function hf_get_datetime_from( $variable_date_type ) {

	try {
		if ( empty( $variable_date_type ) ) {
			$datetime = null;
		} elseif ( is_a( $variable_date_type, 'WC_DateTime' ) ) {
			$datetime = $variable_date_type;
		} elseif ( is_numeric( $variable_date_type ) ) {
			$datetime = new WC_DateTime( "@{$variable_date_type}", new DateTimeZone( 'UTC' ) );
			$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$datetime = new WC_DateTime( $variable_date_type, new DateTimeZone( wc_timezone_string() ) );
		}
	} catch ( Exception $e ) {
		$datetime = null;
	}

	return $datetime;
}

public static function convert_date_to_time( $date_string ) {

	if ( 0 == $date_string ) {
		return 0;
	}
	$date_obj = new DateTime( $date_string, new DateTimeZone( 'UTC' ) );
	return intval( $date_obj->format( 'U' ) );
}

public static function is_mysql_datetime_format( $time ) {
    
	if ( ! is_string( $time ) ) {
		return false;
	}

	if ( function_exists( 'strptime' ) ) {
		$valid_time = $match = ( false !== strptime( $time, '%Y-%m-%d %H:%M:%S' ) ) ? true : false;
	} else {
		$match = preg_match( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time );
		$valid_time = self::convert_date_to_time( $time );
	}
	return ( $match && false !== $valid_time && -2209078800 <= $valid_time ) ? true : false;
}
    
    public static function hforce_get_subscription_ranges($subscription_period = '') {

        if (!is_string($subscription_period)) {
            $subscription_period = '';
        }
        
        $locale = get_locale();
        $subscription_ranges = HForce_Woocommerce_Subscription_Admin::get_subscription_ranges('hf-sub-ranges-' . $locale, 'get_original_subscription_ranges', array(), 3 * HOUR_IN_SECONDS);        
        $subscription_ranges = apply_filters('hf_subscription_lengths', $subscription_ranges, $subscription_period);

        return (!empty($subscription_period) ) ? $subscription_ranges[$subscription_period] : $subscription_ranges;
    }

    public static function get_subscription_period_interval_strings($interval = '') {

        $intervals = array(1 => __('every', 'xa-woocommerce-subscription'));
        $range = array ( 0 => 2, 1 => 3, 2 => 4, 3 => 5, 4 => 6, );
        
        foreach ($range as $i) {
            $intervals[$i] = sprintf(__('every %s', 'xa-woocommerce-subscription'), HForce_Woocommerce_Subscription_Admin::append_numeral_suffix($i));
        }

        $intervals = apply_filters('hf_subscription_period_interval_strings', $intervals);

        if (empty($interval)) {
            return $intervals;
        } else {
            return $intervals[$interval];
        }
    }

    public static function get_available_time_periods($form = 'singular') {

        $number = ( 'singular' === $form ) ? 1 : 2;

        $translated_periods = apply_filters('hf_subscription_available_time_periods', array(
            'day' => _nx('day', 'days', $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', 'xa-woocommerce-subscription'),
            'week' => _nx('week', 'weeks', $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', 'xa-woocommerce-subscription'),
            'month' => _nx('month', 'months', $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', 'xa-woocommerce-subscription'),
            'year' => _nx('year', 'years', $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', 'xa-woocommerce-subscription'),
                )
        );

        return $translated_periods;
    }

    public static function get_next_timestamp($number_of_periods, $period, $from_timestamp) {

        if ($number_of_periods > 0) {
            if ('month' == $period) {
                $next_timestamp = self::hforce_add_months($from_timestamp, $number_of_periods);
            } else {
                $next_timestamp = self::strtotime_zonebased("+ {$number_of_periods} {$period}", $from_timestamp);
            }
        } else {
            $next_timestamp = $from_timestamp;
        }

        return $next_timestamp;
    }

    public static function hforce_add_months($from_timestamp, $months_to_add) {

        $first_day_of_month = gmdate('Y-m', $from_timestamp) . '-1';
        $days_in_next_month = gmdate('t', self::strtotime_zonebased("+ {$months_to_add} month", self::date_to_time($first_day_of_month)));

        if (gmdate('d m Y', $from_timestamp) === gmdate('t m Y', $from_timestamp) || gmdate('d', $from_timestamp) > $days_in_next_month) {
            for ($i = 1; $i <= $months_to_add; $i++) {
                $next_month = self::get_next_timestamp(3, 'days', $from_timestamp);
                $next_timestamp = $from_timestamp = self::date_to_time(gmdate('Y-m-t H:i:s', $next_month));
            }
        } else {
            $next_timestamp = self::strtotime_zonebased("+ {$months_to_add} month", $from_timestamp);
        }

        return $next_timestamp;
    }

    public static function is_datetime_mysql_format($time) {

        // DateTime::createFromFormat('Y-m-d H:i:s', '2038-01-19 03:14:07') check is true or false
        if (!is_string($time)) {
            return false;
        }

        if (function_exists('strptime')) {
            $valid_time = $match = ( false !== strptime($time, '%Y-%m-%d %H:%M:%S') ) ? true : false;
        } else {
            $match = preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time);
            $valid_time = self::date_to_time($time);
        }

        return ( $match && false !== $valid_time && -2209078800 <= $valid_time ) ? true : false;
    }

    public static function date_to_time($date_string) {

        if (0 == $date_string) { return 0; }
        $date_obj = new DateTime($date_string, new DateTimeZone('UTC'));
        return intval($date_obj->format('U'));
    }

    public static function hf_date_input($timestamp = 0, $args = array()) {

    $args = wp_parse_args($args, array('name_attr' => '','include_time' => true,));

    $date = ( 0 !== $timestamp ) ? date_i18n('Y-m-d', $timestamp) : '';
    $date_input = '<input type="text" class="date-picker hf-subscriptions" placeholder="' . esc_attr__('YYYY-MM-DD', 'xa-woocommerce-subscription') . '" name="' . esc_attr($args['name_attr']) . '" id="' . esc_attr($args['name_attr']) . '" maxlength="10" value="' . esc_attr($date) . '" pattern="([0-9]{4})-(0[1-9]|1[012])-(##|0[1-9#]|1[0-9]|2[0-9]|3[01])"/>';

    if (true === $args['include_time']) {
        $hours = ( 0 !== $timestamp ) ? date_i18n('H', $timestamp) : '';
        $hour_input = '<input type="text" class="hour" placeholder="' . esc_attr__('HH', 'xa-woocommerce-subscription') . '" name="' . esc_attr($args['name_attr']) . '_hour" id="' . esc_attr($args['name_attr']) . '_hour" value="' . esc_attr($hours) . '" maxlength="2" size="2" pattern="([01]?[0-9]{1}|2[0-3]{1})" />';
        $minutes = ( 0 !== $timestamp ) ? date_i18n('i', $timestamp) : '';
        $minute_input = '<input type="text" class="minute" placeholder="' . esc_attr__('MM', 'xa-woocommerce-subscription') . '" name="' . esc_attr($args['name_attr']) . '_minute" id="' . esc_attr($args['name_attr']) . '_minute" value="' . esc_attr($minutes) . '" maxlength="2" size="2" pattern="[0-5]{1}[0-9]{1}" />';
        $date_input = sprintf('%s@%s:%s', $date_input, $hour_input, $minute_input);
    }

    $timestamp_utc = ( 0 !== $timestamp ) ? $timestamp - get_option('gmt_offset', 0) * HOUR_IN_SECONDS : $timestamp;
    $date_input = '<div class="hf-date-input">' . $date_input . '</div>';

    return apply_filters('hf_subscriptions_date_input', $date_input, $timestamp, $args);
    }
    

    public static function get_datetime_utc_string($datetime) {

        $date = clone $datetime;
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('Y-m-d H:i:s');
    }

    public static function add_prefix_key($key, $prefix = '_') {

        return ( substr($key, 0, strlen($prefix)) != $prefix ) ? $prefix . $key : $key;
    }

    
    public static function get_longest_possible_period($current_period, $new_period) {

        if (empty($current_period) || 'year' == $new_period) {
            $longest_period = $new_period;
        } elseif ('month' === $new_period && in_array($current_period, array('week', 'day'))) {
            $longest_period = $new_period;
        } elseif ('week' === $new_period && 'day' === $current_period) {
            $longest_period = $new_period;
        } else {
            $longest_period = $current_period;
        }
        return $longest_period;
    }
    
    public static function get_shortest_possible_period($current_period, $new_period) {

        if (empty($current_period) || 'day' == $new_period) {
            $shortest_period = $new_period;
        } elseif ('week' === $new_period && in_array($current_period, array('month', 'year'))) {
            $shortest_period = $new_period;
        } elseif ('month' === $new_period && 'year' === $current_period) {
            $shortest_period = $new_period;
        } else {
            $shortest_period = $current_period;
        }
        return $shortest_period;
    }

    // display a human raedable time diff for a given timestamp, eg:- "In 8 hours" , "8 hours ago"...
    public static function get_humanreadable_time_diff( $timestamp_gmt ) {

	$time_diff = $timestamp_gmt - current_time( 'timestamp', true );

	if ( $time_diff > 0 && $time_diff < WEEK_IN_SECONDS ) {
		$date_to_display = sprintf( __( 'In %s', 'xa-woocommerce-subscription' ), human_time_diff( current_time( 'timestamp', true ), $timestamp_gmt ) );
	} elseif ( $time_diff < 0 && absint( $time_diff ) < WEEK_IN_SECONDS ) {
		$date_to_display = sprintf( __( '%s ago', 'xa-woocommerce-subscription' ), human_time_diff( current_time( 'timestamp', true ), $timestamp_gmt ) );
	} else {
		$timestamp_site  = self::date_to_time( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp_gmt ) ) );
		$date_to_display = date_i18n( wc_date_format(), $timestamp_site ) . ' ' . date_i18n( wc_time_format(), $timestamp_site );
	}

	return $date_to_display;
}

    
    public static function strtotime_zonebased($time_string, $from_timestamp = null) {

        $original_timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        if (null === $from_timestamp) {
            $next_timestamp = strtotime($time_string);
        } else {
            $next_timestamp = strtotime($time_string, $from_timestamp);
        }
        date_default_timezone_set($original_timezone);
        return $next_timestamp;
    }
    
    
    
    
    
    public static function hforce_estimate_periods_between( $start_timestamp, $end_timestamp, $unit_of_time = 'month', $rounding_method = 'ceil' ) {

	if ( $end_timestamp <= $start_timestamp ) {

		$periods_until = 0;

	} elseif ( 'month' == $unit_of_time ) {

		// Calculate the number of times this day will occur until we'll be in a time after the given timestamp
		$timestamp = $start_timestamp;

		if ( 'ceil' == $rounding_method ) {
			for ( $periods_until = 0; $timestamp < $end_timestamp; $periods_until++ ) {
				$timestamp = self::hforce_add_months( $timestamp, 1 );
			}
		} else {
			for ( $periods_until = -1; $timestamp <= $end_timestamp; $periods_until++ ) {
				$timestamp = self::hforce_add_months( $timestamp, 1 );
			}
		}
	} else {

		$seconds_until_timestamp = $end_timestamp - $start_timestamp;

		switch ( $unit_of_time ) {

			case 'day' :
				$denominator = DAY_IN_SECONDS;
				break;

			case 'week' :
				$denominator = WEEK_IN_SECONDS;
				break;

			case 'year' :
				$denominator = YEAR_IN_SECONDS;
				
				$seconds_until_timestamp = $seconds_until_timestamp - self::hforce_number_of_leap_days( $start_timestamp, $end_timestamp ) * DAY_IN_SECONDS;
				break;
		}

		$periods_until = ( 'ceil' == $rounding_method ) ? ceil( $seconds_until_timestamp / $denominator ) : floor( $seconds_until_timestamp / $denominator );
	}

	return $periods_until;
}

public static function hforce_number_of_leap_days( $start_timestamp, $end_timestamp ) {
	if ( ! is_numeric( $start_timestamp ) || ! is_numeric( $end_timestamp ) ) {
		throw new InvalidArgumentException( 'Start or end times are not integers' );
	}
	
	$default_tz = date_default_timezone_get();
	date_default_timezone_set( 'UTC' );

	
	$years = range( date( 'Y', $start_timestamp ), date( 'Y', $end_timestamp ) );
	$leap_years = array_filter( $years, 'Hforce_Date_Time_Utils::hforce_is_leap_year' );
	$feb_29s = 0;

	if ( ! empty( $leap_years ) ) {
		
		$first_feb_29 = mktime( 23, 59, 59, 2, 29, reset( $leap_years ) );
		$last_feb_29 = mktime( 0, 0, 0, 2, 29, end( $leap_years ) );

		$is_first_feb_covered = ( $first_feb_29 >= $start_timestamp ) ? 1: 0;
		$is_last_feb_covered = ( $last_feb_29 <= $end_timestamp ) ? 1: 0;

		if ( count( $leap_years ) > 1 ) {
			
			$feb_29s = count( $leap_years ) - 2 + $is_first_feb_covered + $is_last_feb_covered;
		} else {
			$feb_29s = ( $first_feb_29 >= $start_timestamp && $last_feb_29 <= $end_timestamp ) ? 1: 0;
		}
	}
	date_default_timezone_set( $default_tz );

	return $feb_29s;
}
   public static function hforce_is_leap_year( $year ) {
	return date( 'L', mktime( 0, 0, 0, 1, 1, $year ) );
} 
    
    
    
}

new Hforce_Date_Time_Utils();