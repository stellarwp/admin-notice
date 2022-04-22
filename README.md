# StellarWP Admin Notice

[![CI Pipeline](https://github.com/stellarwp/admin-notice/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/stellarwp/admin-notice/actions/workflows/continuous-integration.yml)

This library exposes an object-oriented interface around WordPress' admin notices.

## Why is this useful?

Normally, WordPress plugins have to resort to manually constructing admin notices:

```php
function myplugin_render_my_notice() {
    echo '<div class="notice notice-info"><p>';
    esc_html_e('This is the message body.', 'some-plugin');
    echo '</p></div>';
}

add_action('admin_notices', 'myplugin_render_my_notice');
```

Unfortunately, this starts getting really messy if you want to conditionally add classes (e.g. `.inline`, `.notice-alt`, etc.) or [check user capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/). Furthermore, WordPress doesn't provide a consistent way to track whether or not a particular notice has been dismissed, forcing each plugin to come up with its own solution.

This library sets out to solve that problem, giving you a fluent API for constructing and rendering admin notices in your plugins:

```php
use StellarWP\AdminNotice\AdminNotice;

$notice = AdminNotice::factory(__('This is the message body.', 'some-plugin'), 'info')
    ->setCapability('manage_options')
    ->setDismissible(true)
    ->queue();
```

With just a few lines of code, you have a dismissible, info-level notice that will get displayed automatically on "[admin_notices](https://developer.wordpress.org/reference/hooks/admin_notices/)", but **only** if the current user has the "manage_options" capability!

## Installation

It's strongly recommended that you install this library as a project dependency via [Composer](https://getcomposer.org):

```sh
$ composer require stellarwp/admin-notice
```

In order to prevent conflicts between your code and other plugins or themes that might implement this library, it's **strongly** recommended that you take advantage of [PHP Autoloading](https://code.tutsplus.com/tutorials/how-to-autoload-classes-with-composer-in-php--cms-35649) and **do not** `include_once` or `require_once` these files!

## Usage

The most basic version of the `AdminNotice` class is as follows:

```php
use StellarWP\AdminNotice\AdminNotice;

$notice = new AdminNotice('Hello, world!');
```

> 💡 To make it easier to construct notices fluently, you may also use the static `AdminNotice::factory()` method, which accepts the same arguments as `new AdminNotice()`.

This `$notice` object represents an info-level notice, whose markup will look something like this:

```html
<div class="notice notice-info stellarwp-admin-notice">
    <p>Hello, world!</p>
</div>
```

There are two ways of getting the markup for this notice:

1. [`$notice->render()`](#render-string): This method return the markup as a string.
2. [`$notice->display()`](#display-void): This method will print the markup to the screen, and is essentially an alias of `echo $notice->render();`

> 💡 The `AdminNotice` class implements [the `__toString()` magic method](https://www.php.net/manual/en/language.oop5.magic.php#object.tostring), so you may also write something like `echo $notice`.

### Admin notice levels

WordPress supports four levels of admin notices:

1. `success` (green color scheme)
2. `warning` (yellow color scheme)
3. `error` (red color scheme)
4. `info` (blue color scheme)

For your convenience, the following constants are available on the `AdminNotice` class, to be passed to the second argument of the class constructor:

* `AdminNotice::TYPE_SUCCESS`
* `AdminNotice::TYPE_WARNING`
* `AdminNotice::TYPE_ERROR`
* `AdminNotice::TYPE_INFO`

```php
use StellarWP\AdminNotice\AdminNotice;

# Create a success message.
AdminNotice::factory('Operation completed sucessfully!', AdminNotice::TYPE_SUCCESS);

# Create a warning message.
AdminNotice::factory('One or more settings are missing, falling back to defaults.', AdminNotice::TYPE_WARNING);

# Create an error message.
AdminNotice::factory('You do not have permission to perform this action.', AdminNotice::TYPE_ERROR);

# Create an info message (second argument optional)
AdminNotice::factory('This is for your information:', AdminNotice::TYPE_INFO);
```

### Rendering admin notices

Now, we need a way to render the notice, typically during [the "admin_notices" action](https://developer.wordpress.org/reference/hooks/admin_notices/).

Admin notices may be queued in a few different ways:

1. Explicitly call [`add_action()`](https://developer.wordpress.org/reference/functions/add_action/) with the notice's `display()` method as the callback:

    ```php
    add_action('admin_notices, [$notice, 'display']);
    ```

2. Call the `queue()` method on the notice itself:

    ```php
    AdminNotice::factory('Some message', 'info')->queue();
    ```

3. Write your own handler that may work with the notice instance.

### Remembering dismissed notices

Often, it can be helpful to remember if a user has dismissed a particular notice. For this reason, [`AdminNotice::setDismissible()` accepts two arguments](#setdismissiblebool-dismissible-string-key--null-self):

1. Whether or not a notice may be dismissed.
2. An optional key for tracking dismissals.

If a dismissible admin notice with a key is rendered, `AdminNotice` will automatically enqueue JavaScript to fire an AJAX request when such notices are dismissed: once a user dismisses the notice, the key and timestamp are stored in their user meta.

The **only** thing the `AdminNotice` can't do itself is queue up the AJAX handler. Instead, it's necessary to call `StellarWP\AdminNotice\DismissalHandler::listen()` _somewhere_ in your plugin's setup process (wherever you choose to register your hooks).

#### Example

This example will display the notice with key "some-unique-key" until the user dismisses it.

```php
use StellarWP\AdminNotice\AdminNotice;
use StellarWP\AdminNotice\DismissalHandler;

// Somewhere in your code, make sure you're listening for the AJAX event.
DismissalHandler::listen();

// Create the dismissible admin notice.
AdminNotice::factory('This notice may be dismissed')
    ->setDismissible(true, 'some-unique-key')
    ->queue();
```

### Checking user capabilities

Often, admin notices are only meant for a small sub-set of users. For example, there isn't much reason to tell **all** users about a new, premium extension for your plugin if only administrator-level users can install plugins.

For this reason, the `AdminNotice` class lets notices be wrapped in [a `current_user_can()` check](https://developer.wordpress.org/reference/functions/current_user_can/):

```php
# Only show this notice if `current_user_can('install_plugins')` returns true.
AdminNotice::factory('Hey there, you should install this plugin', 'info')
    ->setCapability('install_plugins');
```

## The full AdminNotice API

These are all of the methods available for constructing your admin notices:

### `__construct(string $message[, string $type = 'info'])`

Construct a new AdminNotice instance.

#### Arguments

<dl>
    <dt>string $message</dt>
    <dd>The body of the admin notice.</dd>
    <dd>This may contain HTML, and will be run through <a href="https://developer.wordpress.org/reference/functions/wpautop/"><code>wpautop()</code></a> and <a href="https://developer.wordpress.org/reference/functions/wp_kses_post/"><code>wp_kses_post()</code></a> prior to rendering.</dd>
    <dt>string $type</dt>
    <dd>Optional. The type of notice, one of "success", "error", "warning", or "info". Default is "info".</dd>
</dl>

See also: [`AdminNotice::factory()`](#static-factorystring-message-string-type--info-adminnotice)

### `dismissedByUser(?int $user = null): bool`

Check to see if this notice has been dismissed by the given user. Will return true if the user has previously dismissed this notice, false otherwise.

#### Arguments

<dl>
    <dt>?int $user</dt>
    <dd>Optional. The user ID. Default is null (the current user).</dd>
</dl>

### `dismissedByUserAt(?int $user = null): DateTimeImmutable`

Retrieve a DateTime object representing when the given user [last] dismissed this notice.

The method will return a `DateTimeImmutable` object representing when the notice was dismissed, or `NULL` if the user has not dismissed this notice.

#### Arguments

<dl>
    <dt>?int $user</dt>
    <dd>Optional. The user ID. Default is null (the current user).</dd>
</dl>

### `dismissForUser(?int $user = null): self`

Mark a dismissible notice as dismissed by the given user.

This behaves the same as [the static `dismissNoticeForUser()` method](#static-dismissnoticeforuserstring-notice-int--null-bool), but doesn't need to be given the notice key (and won't try to dismiss one if no key is present).

#### Arguments

<dl>
    <dt>?int $user</dt>
    <dd>Optional. The user ID. Default is null (the current user).</dd>
</dl>

### `display(): void`

Render and print the admin notice.

### `queue([int $priority = 10]): self`

Queue this admin notice to be displayed on "admin_notices".

#### Arguments

<dl>
    <dt>int $priority</dt>
    <dd>Optional. The priority to use with <a href="https://developer.wordpress.org/reference/functions/add_action/"><code>add_action()</code></a>. Default is 10.</dd>
</dl>

### `render(): string`

Render the admin notice.

This method is responsible for all of the logic around how the notice's markup gets built, including whether or not it should show anything at all (based on things like [capability checks](#checking-user-capabilities) and/or [dismissal history](#remembering-dismissed-notices)).

### `setAlt(bool $alt): self`

Set whether or not WordPress should use alternate coloring for this notice.

#### Arguments

<dl>
    <dt>bool $alt</dt>
    <dd>True if alternate coloring should be used, false otherwise.</dd>
</dl>

### `setCapability([string $capability = null]): self`

Define a capability check that must be satisfied before rendering this notice.

#### Arguments

<dl>
    <dt>?string $capability</dt>
    <dd>A capability that the current user must possess. Passing <code>NULL</code> will remove any existing capability requirements.</dd>
</dl>

### `setDismissible(bool $dismissible[, ?string $key = null]): self`

Set whether or not this notice should be [dismissible by the user](#remembering-dismissed-notices).

#### Arguments

<dl>
    <dt>bool $dismissible</dt>
    <dd>Whether or not the notice may be dismissed by the user.</dd>
    <dt>?string $key = null</dt>
    <dd>Optional. A unique key identifying this notice. Default is null (do not track dismissal).</dd>
    <dd>Once a user has dismissed a notice with this ID, future notices with the same ID will not be rendered.</dd>
</dl>

### `setInline(bool $inline): self`

Specify whether or not a notice should be rendered inline or pulled to the top of the page (default).

#### Arguments

<dl>
    <dt>bool $inline</dt>
    <dd>True if the notice should be rendered inline, false otherwise.</dd>
</dl>

### `static dismissNoticeForUser(string $notice[, ?int = null]): bool`

Mark an individual notice as dismissed for the given user ID.

Will return true if the notice has been added to the user's list of dismissed notices, false otherwise.

#### Arguments

<dl>
    <dt>string $notice</dt>
    <dd>The admin notice's dismissible key.</dd>
    <dt>int $user</dt>
    <dd>Optional. The user ID. Default is null (the current user).</dd>
</dl>

### `static factory(string $message[, string $type = 'info']): AdminNotice`

This method is an alias for [the class constructor](#constructstring-message-string-type--info) (and accepts the same arguments), making it easier to write fluent strings.

Both notices in the following are equivalent:

```php
# Using the constructor directly requires extra parentheses.
(new AdminNotice('Some message', 'info'))
    ->setInline(true)
    ->queue();

# The ::factory() method removes this constraint.
AdminNotice::factory('Some message', 'info')
    ->setInline(true)
    ->queue();
```

## Contributing

If you're interested in contributing to the project, please [see our contributing documentation](.github/CONTRIBUTING.md).

## License

This library is licensed under the terms of [the MIT license](LICENSE.txt).
