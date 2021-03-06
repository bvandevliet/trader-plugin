<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * User specific rebalance configuration.
 */
class Configuration
{
  /**
   * A "stable" asset to hold a fixed percentage of.
   *
   * @var string|null
   */
  public ?string $sideline_currency = \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

  /**
   * Allocation percentage to keep in sideline currency.
   *
   * @var int|float|string
   */
  public $alloc_sideline = 5;

  /**
   * Alternative asset allocation weighting factors.
   *
   * @var array
   */
  public array $asset_weightings = array();

  /**
   * Exclude assets that have at least one of these tags.
   *
   * @var string[]
   */
  public array $excluded_tags = array( 'stablecoin' );

  /**
   * Amount of assets from the top market cap ranking.
   *
   * @var int
   */
  public int $top_count = 10;

  /**
   * The period to use for smoothing Market Cap.
   *
   * @var int
   */
  public int $smoothing = 3;

  /**
   * The nth root of Market Cap to use for allocation.
   *
   * @var int|float|string
   */
  public $nth_root = '2.4';

  /**
   * Minimum required allocation difference in quote currency.
   *
   * @var int
   *
   * @deprecated If alloc diff is below min order amount, just skip.
   */
  // public int $dust_limit = 5;

  /**
   * Amount in quote currency to keep out / not re-invest.
   *
   * @var int|float|string
   */
  public $takeout = 0;

  /**
   * Rebalance period in hours.
   *
   * @var int
   */
  public int $interval_hours = 8;

  /**
   * Rebalance allocation percentage difference threshold.
   *
   * @var int|float|string
   */
  public $rebalance_threshold = '1.2';

  /**
   * Rebalance mode.
   *
   * @var string
   */
  public string $rebalance_mode = 'default';

  /**
   * Automatic periodic rebalancing.
   *
   * @var bool
   */
  public bool $automation_enabled = false;

  /**
   * Last rebalance timestamp.
   *
   * @var \DateTime|null
   */
  public ?\DateTime $last_rebalance = null;

  /**
   * Creates an instance and optionally pre-define properties.
   * Use the get() method instead if you intend to save() this configuration, to avoid overwrites.
   *
   * @param array|object $object Optional array or object of args to parse into the instance.
   */
  public function __construct( array|object $object = array() )
  {
    foreach ( (array) $object as $key => $value ) {
      $this->$key = $value;
    }
  }

  /**
   * Configurations cache.
   *
   * FOR NOW, ONLY ONE CONFIGURATION PER USER IS SUPPORTED !!
   *
   * @var self[]
   */
  private static array $configurations = array();

  /**
   * Get configuration by user id.
   *
   * @param null|int $user_id Defaults to current user.
   *
   * @return self
   */
  public static function &get( ?int $user_id = null ) : self
  {
    $user_id = $user_id ?? wp_get_current_user()->ID;

    if ( empty( self::$configurations[ $user_id ] ) ) {
      $configuration                    = get_user_meta( $user_id, 'trader_configuration', true );
      self::$configurations[ $user_id ] = $configuration instanceof self ? $configuration : new self();
    }

    $configuration =& self::$configurations[ $user_id ];

    // back-compat
    if ( 0 === count( $configuration->asset_weightings ) ) {
      $asset_weightings                = get_user_meta( $user_id, 'asset_weightings', true );
      $configuration->asset_weightings = is_array( $asset_weightings ) ? $asset_weightings : array();
    }

    return $configuration;
  }

  /**
   * Get all configurations that have automation enabled.
   *
   * @link https://developer.wordpress.org/reference/functions/update_meta_cache/
   *
   * @global wpdb $wpdb
   *
   * @return self[][] Associative array of automated configurations indexed by user ID.
   */
  public static function get_automations() : array
  {
    global $wpdb;

    $automations = array();

    $table = _get_meta_table( 'user' );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $meta_list = $wpdb->get_results( "SELECT user_id, meta_value FROM $table WHERE meta_key = 'trader_configuration' ORDER BY umeta_id ASC", ARRAY_A );

    if ( empty( $meta_list ) || ! is_array( $meta_list ) ) {
      return $automations;
    }

    foreach ( $meta_list as $metarow ) {
      /**
       * @var self
       */
      $configuration = maybe_unserialize( $metarow['meta_value'] );

      // Only add automated configurations.
      if ( ! $configuration->automation_enabled ) {
        continue;
      }

      $user_id = (int) $metarow['user_id'];

      // Force subkeys to be array type.
      if ( ! isset( $automations[ $user_id ] ) || ! is_array( $automations[ $user_id ] ) ) {
        $automations[ $user_id ] = array();
      }

      // Add a value to the current pid/key.
      $automations[ $user_id ][] = $configuration;
    }

    return $automations;
  }

  /**
   * Get rebalance parameters from request parameters or a passed object.
   *
   * @param array|object $object  Optional array or object of args to parse into the instance.
   * @param null|int     $user_id Defaults to current user.
   *
   * @return self
   */
  public static function get_configuration_from_environment( array|object $object = array(), ?int $user_id = null ) : self
  {
    $configuration = self::get( $user_id );

    // back-compat
    if ( isset( $configuration->alloc_quote ) ) {
      $configuration->alloc_sideline = $configuration->alloc_quote;
      unset( $configuration->alloc_quote );
    }

    /**
     * If an object was passed or current request is a POST request, then reset default values to initial values.
     * This is required since empty fields or checkboxes may not be serialized at all while their default values may be defined otherwise.
     * Ideally, the form should explicitly set all of the below parameters to ensure expected outcome.
     */
    if ( count( (array) $object ) > 0 || 'POST' === $_SERVER['REQUEST_METHOD'] ) {
      foreach ( array(
        'sideline_currency'   => null,
        'top_count'           => 1,
        'smoothing'           => 1,
        'nth_root'            => 1,
        'alloc_sideline'      => 0,
        'takeout'             => 0,
        'interval_hours'      => 1,
        'rebalance_threshold' => 0,
        'rebalance_mode'      => 'default',
        'automation_enabled'  => false,
      ) as $param => $initial ) {
        $configuration->$param = $initial;
      }
    }

    foreach ( (array) $configuration as $param => $default ) {
      // phpcs:ignore WordPress.Security
      $req_value = wp_unslash( ( (array) $object )[ $param ] ?? $_POST[ $param ] ?? $_GET[ $param ] ?? null );
      switch ( $param ) {
        case 'sideline_currency':
          $configuration->$param = isset( $req_value ) ? strtoupper( sanitize_key( $req_value ) ) : $default;
          break;
        case 'top_count':
          $configuration->$param = isset( $req_value ) ? min( max( 1, intval( $req_value ) ), 100 ) : $default;
          break;
        case 'smoothing':
        case 'interval_hours':
          $configuration->$param = isset( $req_value ) ? max( 1, intval( $req_value ) ) : $default;
          break;
        case 'nth_root':
          $configuration->$param = is_numeric( $req_value ) ? trader_max( 1, floatstr( $req_value ) ) : $default;
          break;
        case 'alloc_sideline':
        case 'rebalance_threshold':
          // ("99" to avoid divide by zero issues) ..
          $configuration->$param = is_numeric( $req_value ) ? trader_min( trader_max( 0, floatstr( $req_value ) ), 99 ) : $default;
          break;
        case 'takeout':
          $configuration->$param = is_numeric( $req_value ) ? trader_max( 0, floatstr( $req_value ) ) : $default;
          break;
        case 'rebalance_mode':
          $configuration->$param = isset( $req_value ) ? sanitize_key( $req_value ) : $default;
          break;
        case 'automation_enabled':
          $configuration->$param = ! empty( $req_value ) ? boolval( $req_value ) : $default;
          break;
        default:
          $configuration->$param = is_numeric( $req_value ) && is_numeric( $default ) ? floatstr( $req_value ) : $default;
      }
    }

    return $configuration;
  }

  /**
   * Save this configuration.
   *
   * @param null|int $user_id Defaults to current user.
   */
  public function save( ?int $user_id = null )
  {
    $user_id = $user_id ?? wp_get_current_user()->ID;

    // back-compat
    delete_user_meta( $user_id, 'asset_weightings' );

    update_user_meta( $user_id, 'trader_configuration', $this );
  }
}
