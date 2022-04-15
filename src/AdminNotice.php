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
    protected $dismissibleKey;

    /**
     * Whether or not this notice should be rendered inline.
     *
     * @var bool
     */
    protected $inline = false;

    /**
     * The body of the admin notice.
     *
     * @var string
     */
    protected $message;

    /**
     * The type of notice, one of "success", "error", "warning", or "info".
     *
     * @var self::TYPE_*
     */
    protected $type = self::TYPE_INFO;

    /**
     * The nonce action name used for notice dismissal.
     */
    const NONCE_DISMISS_NOTICE = 'stellarwp-admin-notice-dismiss';

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
     * Construct a new AdminNotice.
     *
     * @param string       $message The body of the message. This may contain HTML, but plain text will
     *                              automatically be wrapped in paragraph tags.
     * @param self::TYPE_* $type    Optional. The type of notice, one of "success", "error", "warning",
     *                              or "info". Default is "info".
     */
    public function __construct($message, $type = self::TYPE_INFO)
    {
        $this->message = $message;
        $this->type    = $this->validateType($type);
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
        throw new ImmutableValueException(sprintf(
            'Properties on %1$s cannot be modified directly. Please use the set*() methods instead.',
            __CLASS__
        ));
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
        if ($this->capability && ! current_user_can($this->capability)) {
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
                    wp_create_nonce(static::NONCE_DISMISS_NOTICE),
                );
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
     * Set the ID for the admin notice.
     *
     * @param string $id The notice ID.
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

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
     * Set type type of admin notice.
     *
     * @param self::TYPE_* $type The type, one of "success", "error", "warning", or "info".
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $this->validateType($type);

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
}
