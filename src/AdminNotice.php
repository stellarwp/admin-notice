<?php

namespace StellarWP\AdminNotice;

use StellarWP\AdminNotice\Exceptions\ImmutableValueException;

/**
 * An object-oriented representation of a WordPress admin notice (e.g. ".notice"), to be queued via
 * the "admin_notices" action.
 *
 * @link https://developer.wordpress.org/reference/hooks/admin_notices/
 *
 * @property-read bool    $alt            Whether or not this notice should use alternate coloring.
 * @property-read ?string $capability     A capability that the current user must possess in order to see this notice.
 * @property-read bool    $dismissible    Whether or not this notice should be dismissible by the user.
 * @property-read ?string $dismissibleKey A unique key for this notice, for the sake of tracking dismissals.
 * @property-read bool    $inline         Whether or not this notice should be rendered inline.
 * @property-read string  $message        The body of the admin notice.
 * @property-read string  $type           The type of notice, one of "success", "error", "warning", or "info".
 */
class AdminNotice
{
    /**
     * Whether or not this notice should use alternate coloring.
     *
     * @var bool
     */
    protected $alt = false;

    /**
     * A capability that the current user must possess in order to see this notice.
     *
     * @var ?string
     */
    protected $capability;

    /**
     * Whether or not this notice should be dismissible by the user.
     *
     * @var bool
     */
    protected $dismissible = false;

    /**
     * A unique key for this notice, for the sake of tracking dismissals.
     *
     * @var ?string
     */
    public $dismissibleKey;

    /**
     * Whether or not this notice should be rendered inline.
     *
     * @var bool
     */
    protected $inline = false;

    /**
     * Whether or not this notice is persistence, used for backwards compatability with NXMU
     *
     * @var bool
     */
    protected $persistence = false;

    /**
     * The body of the admin notice.
     *
     * @var string
     */
    protected $message;

    /**
     * Added for backwards compatability with NXMU
     *
     * @var bool
     */
    protected $save_dismissal;

    /**
     * Whether or not this notice should be delayed.
     *
     * @var bool
     */
    protected $delayed = false;

    /**
     * The type of delay, either 'user' or 'site'.
     *
     * @var string
     */
    protected $delayed_type = 'user';

    /**
     * Amount of time to delay the delayed notice.
     *
     * @var int
     */
    protected $delayed_time = 0;

    /**
     * The type of notice, one of "success", "error", "warning", or "info".
     *
     * @var self::TYPE_*
     */
    protected $type = self::TYPE_INFO;

    /**
     * Transient that holds persisted notices.
     */
    const PERSISTENT_NOTICES_CACHE_KEY = 'persistent_admin_notices';

    /**
     * The Ajax action for dismissals.
     */
    const ACTION_DISMISSAL = 'stellarwp-dismiss-notice';

    /**
     * The nonce action name used for notice dismissal.
     */
    const NONCE_DISMISS_NOTICE = 'stellarwp-admin-notice-dismiss';

    /**
     * The handle used when registering JavaScript for handling dismissals.
     */
    const SCRIPT_HANDLE = 'stellarwp-admin-notice';

    /**
     * Red color scheme, typically used to indicate a failed operation or other issue.
     */
    const TYPE_ERROR = 'error';

    /**
     * Blue color scheme, used for general information.
     */
    const TYPE_INFO = 'info';

    /**
     * Green color scheme, typically used to indicate a successful operation.
     */
    const TYPE_SUCCESS = 'success';

    /**
     * Yellow color scheme, generally used to warn a user.
     */
    const TYPE_WARNING = 'warning';

    /**
     * The user meta key that holds the IDs and Unix timestamps of dismissed notices.
     */
    const USER_META_KEY = '_stellarwp_dismissed_notices';

    /**
     * Key for delayed notifications, used either as user meta or site option.
     */
    const DELAYED_NOTICES_KEY = '_stellarwp_delayed_notices';

    /**
     * The version of this library.
     */
    const VERSION = '0.1.0';

    /**
     * Construct a new AdminNotice.
     *
     * @param string       $message         The body of the message. This may contain HTML, but plain text will
     *                                      automatically be wrapped in paragraph tags.
     * @param self::TYPE_* $type            Optional. The type of notice, one of "success", "error", "warning",
     *                                      or "info". Default is "info".
     *
     * Migrated from NXMU for backwards compatability reasons.
     *
     * @param bool         $dismissible     Optional. Whether the notice should be marked as
     *                                      dismissible. Default is true.
     * @param string       $dismissibleKey  Optional. A unique ID for the notification, which is used for
     *                                      tracking dismissed notifications. Default is a hash of $message.
     */
    public function __construct($message, $type = self::TYPE_INFO, $dismissible = false, $dismissibleKey = '')
    {
        $this->message        = $message;
        $this->type           = $this->validateType($type);
        $this->dismissible    = $dismissible;

        if ($dismissible) {
            $this->setDismissible($dismissible, '' === $dismissibleKey ? true : $dismissibleKey);
        }
    }

    /**
     * Enable protected properties to be accessed directly.
     *
     * @param string $prop The property to retrieve.
     *
     * @return mixed Either the value of the property, or null if the property is undefined.
     */
    public function __get($prop)
    {
        return isset($this->{$prop}) ? $this->{$prop} : null;
    }

    /**
     * For the sake of type-safety, properties should be treated as immutable unless modified
     * through the set*() methods.
     *
     * In the future, we'll be able to apply types to our properties and have this enforced at the
     * language level, but that can't happen until WordPress drops support for ancient, end-of-life
     * versions of PHP.
     *
     * @param string $prop  The property name.
     * @param mixed  $value The value that is being assigned.
     *
     * @throws ImmutableValueException Prevent propeties from being written and direct developers to
     *                                 the assorted set*() methods.
     *
     * @return void
     */
    public function __set($prop, $value)
    {
        throw new ImmutableValueException(
            sprintf(
                'Properties on %1$s cannot be modified directly. Please use the set*() methods instead.',
                __CLASS__
            )
        );
    }

    /**
     * Automatically render the notice if it's cast to a string.
     *
     * @return string The admin notice markup.
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Check to see if this notice has been dismissed by the given user.
     *
     * @param ?int $user Optional. The user ID. Default is null (the current user).
     *
     * @return bool True if the user has previously dismissed this notice, false otherwise.
     */
    public function dismissedByUser($user = null)
    {
        return $this->dismissedByUserAt($user) instanceof \DateTimeImmutable;
    }

    /**
     * Retrieve a DateTime object representing when the given user last dismissed this notice.
     *
     * @param ?int $user Optional. The user ID. Default is null (the current user).
     *
     * @return ?\DateTimeImmutable A DateTime object representing when the notice was dismissed,
     *                             or NULL if the user has not dismissed this notice.
     */
    public function dismissedByUserAt($user = null)
    {
        if (empty($this->dismissibleKey)) {
            return null;
        }

        if (null === $user) {
            $user = get_current_user_id();
        }

        $current = get_user_meta($user, self::USER_META_KEY, true);

        if (! is_array($current) || ! isset($current[$this->dismissibleKey])) {
            return null;
        }

        /** @var Array<string,int> $current */
        return \DateTimeImmutable::createFromFormat('U', (string) $current[$this->dismissibleKey]) ?: null;
    }

    /**
     * Mark a dismissible notice as dismissed by the given user.
     *
     * @param ?int $user Optional. The user ID. Default is null (the current user).
     *
     * @return $this
     */
    public function dismissForUser($user = null)
    {
        if (! $this->dismissible || ! $this->dismissibleKey) {
            return $this;
        }

        static::dismissNoticeForUser($this->dismissibleKey, $user);

        return $this;
    }

    /**
     * Render and print the admin notice.
     *
     * @return void
     */
    public function display()
    {
        echo wp_kses_post($this->render());
    }

    /**
     * Queue this admin notice to be displayed on "admin_notices".
     *
     * @param int $priority Optional. The priority to use with add_action(). Default is 10.
     *
     * @return $this
     */
    public function queue($priority = 10)
    {
        add_action('admin_notices', [$this, 'display'], $priority);

        return $this;
    }

    /**
     * Render the admin notice.
     *
     * @return string The admin notice markup.
     */
    public function render()
    {
        // Check user capabilities, if one has been set.
        if ($this->capability && ! current_user_can($this->capability)) {
            return '';
        }

        // Determine if this user has already dismissed this notice.
        if ($this->dismissible && $this->dismissedByUser()) {
            return '';
        }

        // Assemble a list of classes.
        $dataAttributes = '';
        $classes        = [
            'notice',
            "notice-{$this->type}",
            'stellarwp-admin-notice',
        ];

        if ($this->alt) {
            $classes[] = 'notice-alt';
        }

        if ($this->inline) {
            $classes[] = 'inline';
        }

        if ($this->dismissible) {
            $classes[] = 'is-dismissible';

            if ($this->dismissibleKey) {
                $dataAttributes = sprintf(
                    ' data-id="%1$s" data-nonce="%2$s"',
                    $this->dismissibleKey,
                    wp_create_nonce(static::NONCE_DISMISS_NOTICE)
                );

                static::enqueueScript();
            }
        }

        return sprintf(
            '<div class="%1$s"%2$s>%3$s</div>',
            esc_attr(implode(' ', $classes)),
            $dataAttributes,
            wpautop($this->message)
        );
    }

    /**
     * Set whether or not WordPress should use alternate coloring for this notice.
     *
     * @param bool $alt True if alternate coloring should be used, false otherwise.
     *
     * @return $this
     */
    public function setAlt($alt)
    {
        $this->alt = (bool) $alt;

        return $this;
    }

    /**
     * Define a capability check that must be satisfied before rendering this notice.
     *
     * @param ?string $capability A capability that the current user must possess. Passing NULL
     *                            will remove any existing capability requirements.
     *
     * @return $this
     */
    public function setCapability($capability)
    {
        $this->capability = $capability;

        return $this;
    }

    /**
     * Set whether or not this notice should be dismissible by the user.
     *
     * @param bool    $dismissible True if dismissible, false otherwise.
     * @param ?scalar $key         Optional. If present, WordPress will attempt to remember when this
     *                             notice was dismissed. Meanwhile, if given a boolean true, a key
     *                             will automatically be generated for this notice.
     *
     * @return $this
     */
    public function setDismissible($dismissible, $key = null)
    {
        $this->dismissible = (bool) $dismissible;

        if (is_scalar($key) && $key) {
            if (true === $key) {
                $key = $this->type . ':' . mb_substr(md5($this->message), 0, 10);
            }

            $this->dismissibleKey = (string) $key;
        }

        return $this;
    }

    /**
     * Set whether or not this notice should be rendered inline.
     *
     * By default, WordPress will attempt to pull all admin notices to the top of the page.
     *
     * @param bool $inline True if the notice should be rendered inline, false otherwise.
     *
     * @return $this
     */
    public function setInline($inline)
    {
        $this->inline = (bool) $inline;

        return $this;
    }

    /**
     * Compare the given type against valid types and, if not found, return a default ("info").
     *
     * @param string $type The passed admin notice type.
     *
     * @return self::TYPE_* Either the validated $type or self::TYPE_INFO.
     */
    protected function validateType($type)
    {
        $valid = [
            self::TYPE_ERROR,
            self::TYPE_INFO,
            self::TYPE_SUCCESS,
            self::TYPE_WARNING,
        ];

        return in_array($type, $valid, true) ? $type : self::TYPE_INFO;
    }

    /**
     * Mark an individual notice as dismissed for the given user ID.
     *
     * @param string $notice The admin notice's dismissible key.
     * @param ?int   $user Optional. The user ID. Default is null (the current user).
     *
     * @return bool True if the user meta was updated, false otherwise.
     */
    public static function dismissNoticeForUser($notice, $user = null)
    {
        if (null === $user) {
            $user = get_current_user_id();
        }

        if (! $user) {
            return false;
        }

        $current = get_user_meta($user, self::USER_META_KEY, true);

        if (! is_array($current)) {
            $current = [];
        }

        return (bool) update_user_meta($user, self::USER_META_KEY, array_merge($current, [
            $notice => time(),
        ]));
    }

    /**
     * Enqueue the JavaScript necessary for remembering dismissals.
     *
     * @return void
     */
    public static function enqueueScript()
    {
        // If we're not yet to admin_enqueue_scripts, queue this to re-run later.
        if (! did_action('admin_enqueue_scripts')) {
            add_action('admin_enqueue_scripts', __METHOD__);
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            plugins_url('assets/admin-notice.js', __DIR__),
            [],
            self::VERSION,
            true
        );
    }

    /**
     * Construct a new AdminNotice.
     *
     * @param string       $message The body of the message. This may contain HTML, but plain text will
     *                              automatically be wrapped in paragraph tags.
     * @param self::TYPE_* $type    Optional. The type of notice, one of "success", "error", "warning",
     *                              or "info". Default is "info".
     *
     * @return self
     */
    public static function factory($message, $type = self::TYPE_INFO)
    {
        // @phpstan-ignore-next-line As we're explicitly forwarding all method parameters.
        return new static(...func_get_args());
    }

    /**
     * Migrated from Nexccus MU Plugin
     */

    /**
     * Set the value of $save_dismissal.
     *
     * @param bool $save Whether or not to save the dismissal in the user's meta.
     *
     * @return self
     */
    public function setSaveDismissal($save)
    {
        $this->save_dismissal = $save;

        return $this;
    }

    /**
     * Retrieve any persistent admin notices.
     *
     * @return array<AdminNotice>
     */
    public static function getPersistentNotices()
    {
        return array_filter((array) get_transient(self::PERSISTENT_NOTICES_CACHE_KEY) ?: [], function ($notice) {
            return $notice instanceof self;
        });
    }

    /**
     * Add a notice to the list of dismissed notices for a user if it has not already been dismissed.
     *
     * @param int    $user_id   The ID of the WordPress user to check.
     * @param string $notice_id The ID of the notice to check for dismissal.
     *
     * @return int|bool The new meta key ID, true on successful update, false on failure.
     */
    public static function dismissNotice($user_id, $notice_id = '')
    {
        if (self::noticeWasDismissed($user_id, $notice_id)) {
            return true;
        }

        // Track the dismissed notices in user meta.
        $dismissed = (array) get_user_meta($user_id, self::USER_META_KEY, true) ?: [];

        // Add the new notice.
        $dismissed[ $notice_id ] = time();

        return update_user_meta($user_id, self::USER_META_KEY, $dismissed);
    }

    /**
     * Determine whether or not a particular notice should be shown based on the notice ID and the user's previously-
     * dismissed notices.
     *
     * @param int         $user_id    The ID of the WordPress user to check.
     * @param string|null $notice_id  The ID of the notice to check for dismissal.
     *
     * @return bool True if the user has dismissed the notice before or false if the user has not dismissed it.
     */
    public static function noticeWasDismissed($user_id, $notice_id = '')
    {
        $dismissed = (array) get_user_meta($user_id, self::USER_META_KEY, true) ?: [];

        return isset($dismissed[ $notice_id ]);
    }

    /**
     * Set the value of $persistence
     *
     * @param bool $persistence
     *
     * @return $this
     */
    private function setPersistence(bool $persistence)
    {
        $this->persistence = (bool) $persistence;

        return $this;
    }

    /**
     * Persist the notice as a transient.
     *
     * This enables a notice to persist across multiple page loads and redirections.
     *
     * Note that non-dismissible notices will only be displayed once. If the message should be
     * shown on multiple page loads, $this->is_dismissible should be true.
     *
     * @return self
     */
    public function persist()
    {
        $this->setPersistence(true);

        $notices = (array) (get_transient(self::PERSISTENT_NOTICES_CACHE_KEY) ?: []);
        $notices[ $this->dismissibleKey ] = $this;
        set_transient(self::PERSISTENT_NOTICES_CACHE_KEY, $notices, 0);

        return $this;
    }

    /**
     * Determine whether or not a particular notice should be shown based on the user's previously-
     * dismissed notices.
     *
     * @return bool True if the user has dismissed the notice before or false if the user has
     *              either not dismissed it or the notice is not dismissible.
     */
    public function userHasDismissedNotice()
    {
        if (! $this->persistence || ! $this->save_dismissal) {
            return false;
        }

        return self::noticeWasDismissed(get_current_user_id(), $this->dismissibleKey);
    }

    /**
     * Alias for display for backwards compatability with the MUNX admin notice.
     *
     * @return void
     */
    public function output()
    {
        $this->display();
    }

    /**
     * Remove a persistent notice.
     *
     * @return self
     */
    public function forget()
    {
        if ($this->persistence) {
            $notices = (array) (get_transient(self::PERSISTENT_NOTICES_CACHE_KEY) ?: []);
            unset($notices[ $this->dismissibleKey ]);

            // Update the transient, or remove it if it's empty.
            if (empty($notices)) {
                delete_transient(self::PERSISTENT_NOTICES_CACHE_KEY);
            } else {
                set_transient(self::PERSISTENT_NOTICES_CACHE_KEY, $notices, 0);
            }
        }

        return $this;
    }

    /**
     * Getter to return the value of $persistence
     *
     * @return bool
     */
    public function isPersistent()
    {
        return $this->persistence;
    }

    /**
     * Check to see if a notice is delayed and whether or not the delay has expired.
     *
     * @return bool Whether or not the notice is delayed.
     */
    public function noticeIsDelayed()
    {
        // If the notice isn't set as delayed, then it's not delayed.
        if (! $this->delayed) {
            return false;
        }

        // Get the user meta of what notices are currently delayed.
        $delayed_notices = $this->getDelayedNotices();

        // If the notice is not set as delayed, then it's not delayed, so we want to save it as delayed.
        if (empty($delayed_notices[ $this->dismissibleKey ])) {
            return $this->setDelayedNotice();
        }

        // If the notice is delayed, but the delay has expired, then it's not delayed.
        $delay_ends = (int) $delayed_notices[ $this->dismissibleKey ] + $this->delayed_time;

        return $delay_ends > time();
    }

    /**
     * Forget a persistent admin notice by ID.
     *
     * @param string $id The notice ID.
     *
     * @return bool True if the notice was deleted, false otherwise.
     */
    public static function forgetPersistentNotice($id)
    {
        $notices = get_transient(self::PERSISTENT_NOTICES_CACHE_KEY) ?: [];

        // @phpstan-ignore-next-line
        if (! isset($notices[$id])) {
            return false;
        }

        // @phpstan-ignore-next-line
        unset($notices[$id]);

        return set_transient(self::PERSISTENT_NOTICES_CACHE_KEY, $notices);
    }

    /**
     * Set the notice meta data.
     *
     * @return bool Whether or not the notice meta was set.
     */
    public function setDelayedNotice()
    {
        $notices = $this->getDelayedNotices();

        $notices[$this->dismissibleKey] = time();

        if ('user' === $this->delayed_type) {
            return (bool) update_user_meta(get_current_user_id(), self::DELAYED_NOTICES_KEY, $notices);
        }

        if ('site' === $this->delayed_type) {
            return update_site_option(self::DELAYED_NOTICES_KEY, $notices);
        }

        return false;
    }

    /**
     * Get the notice meta data from either user meta or site options.
     *
     * @phpstan-ignore-next-line
     * @return array
     */
    public function getDelayedNotices()
    {
        if ('user' === $this->delayed_type) {
            $notices = get_user_meta(get_current_user_id(), self::DELAYED_NOTICES_KEY, true);
        } elseif ('site' === $this->delayed_type) {
            $notices = get_site_option(self::DELAYED_NOTICES_KEY, []);
        } else {
            $notices = [];
        }

        return (array) $notices;
    }
}
