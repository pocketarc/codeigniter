#######################################
Upgrading from 3.1.x or 3.2-dev to 3.2+
#######################################

This guide covers upgrading to this maintenance fork, which is based on
the unreleased CodeIgniter 3.2.0-dev. Many unnecessary breaking changes
from 3.2.0-dev have been reverted to preserve backward compatibility.
This is the same guide whether you are coming from 3.1.x or 3.2-dev.

Before performing an update you should take your site offline by
replacing the index.php file with a static one.

Step 1: Update your CodeIgniter files
=====================================

Install via Composer (recommended)::

	composer require pocketarc/codeigniter

Then update the ``$system_path`` in your ``index.php``::

	$system_path = 'vendor/pocketarc/codeigniter/system';

Alternatively, you can manually replace all files and directories in
your *system/* directory.

.. note:: If you have any custom developed files in these directories,
	please make copies of them first.

Step 2: Check your PHP version
==============================

This fork supports PHP 5.4 through 8.5+. We recommend running a
`currently supported <https://www.php.net/supported-versions.php>`_
PHP version (8.4 or newer).

Step 3: Calls to ``CI_Model::__construct()`` (optional cleanup)
===============================================================

The class constructor for ``CI_Model`` never contained vital code or useful
logic, only a single line to log a message. A change in CodeIgniter 3.1.7
moved this log message elsewhere and that naturally made the constructor
completely unnecessary.

The constructor is kept as an empty method for backwards compatibility, so
existing code calling ``parent::__construct()`` will continue to work.
However, such calls are unnecessary and can be safely removed.

Step 4: Change database connection handling
===========================================

"Loading" a database, whether by using the *config/autoload.php* settings
or manually via calling ``$this->load->database()`` or the less-known
``DB()`` function, will now throw a ``RuntimeException`` in case of a
failure.

In addition, being unable to set the configured character set is now also
considered a connection failure.

.. note:: This has been the case for most database drivers in the in the
	past as well (i.e. all but the 'mysql', 'mysqli' and 'postgre'
	drivers).

What this means is that if you're unable to connect to a database, or
have an erroneous character set configured, CodeIgniter will no longer
fail silently, but will throw an exception instead.

You may choose to explicitly catch it (and for that purpose you can't use
*config/autoload.php* to load the :doc:`Database Class <../database/index>`)
::

	try
	{
		$this->load->database();
	}
	catch (RuntimeException $e)
	{
		// Handle the failure
	}

Or you may leave it to CodeIgniter's default exception handler, which would
log the error message and display an error screen if you're running in
development mode.

Remove db_set_charset() calls
-----------------------------

With the above-mentioned changes, the purpose of the ``db_set_charset()``
method would now only be to change the connection character set at runtime.
That doesn't make sense and that's the reason why most database drivers
don't support it at all.
Thus, ``db_set_charset()`` is no longer necessary and is removed.

Step 5: Check logic related to URI parsing of CLI requests
==========================================================

When running a CodeIgniter application from the CLI, the
:doc:`URI Library <../libraries/uri>` will now ignore the
``$config['url_suffix']`` and ``$config['permitted_uri_chars']``
configuration settings.

These two options don't make sense under the command line (which is why
this change was made) and therefore you shouldn't be affected by this, but
if you've relied on them for some reason, you'd probably have to make some
changes to your code.

Step 6: Check Cache Library configurations for Redis, Memcache(d)
=================================================================

The new improvements for the 'redis' and 'memcached' drivers of the
:doc:`Cache Library <../libraries/caching>` may require some small
adjustments to your configuration values ...

Redis
-----

If you're using the 'redis' driver with a UNIX socket connection, you'll
have to move the socket path from ``$config['socket']`` to
``$config['host']`` instead.

The ``$config['socket_type']`` option is also removed, although that won't
affect your application - it will be ignored and the connection type will
be determined by the format used for ``$config['host']`` instead.

Memcache(d)
-----------

The 'memcached' will now ignore configurations that don't specify a ``host``
value (previously, it just set the host to the default '127.0.0.1').

Therefore, if you've added a configuration that only sets e.g. a ``port``,
you will now have to explicitly set the ``host`` to '127.0.0.1' as well.

Step 7: Check usage of the Email library
========================================

The :doc:`Email Library <../libraries/email>` will now by default check the
validity of all e-mail addresses passed to it. This check used to be Off by
default, and required explicitly setting the **validate** option to ``TRUE``
in order to enable it.

Naturally, a validity check should not result in any problems, but this is
technically a backwards-compatibility break and you should check that
everything works fine.
If something indeed goes wrong with that, please report it as a bug to us,
and you can disable the **validate** option to revert to the old behavior.

Step 8: Check usage of doctype() HTML helper
============================================

The :doc:`HTML Helper <../helpers/html_helper>` function
:php:func:`doctype()` used to default to 'xhtml1-strict' (XHTML 1.0 Strict)
when no document type was specified. That default value is now changed to
'html5', which obviously stands for the modern HTML 5 standard.

Nothing should be really broken by this change, but if your application
relies on the default value, you should double-check it and either
explicitly set the desired format, or adapt your front-end to use proper
HTML 5 formatting.

Step 9: Check usage of form_upload() Form helper
================================================

The :doc:`Form Helper <../helpers/form_helper>` function
:php:func:`form_upload()` used to have 3 parameters, the second of which
(``$value``) was never used, as it doesn't make sense for an HTML ``input``
tag of the "file" type.

That dead parameter is now removed, and so if you've used the third one
(``$extra``), having code like this::

	form_upload('name', 'irrelevant value', $extra);

You should change it to::

	form_upload('name', $extra);

Step 10: Remove usage of previously deprecated functionalities
==============================================================

The following is a list of functionalities deprecated in previous
CodeIgniter versions that have been removed in 3.2+:

- ``$config['allow_get_array']`` (use ``$_GET = array();`` instead)
- ``$config['standardize_newlines']``
- ``$config['rewrite_short_tags']`` (no impact; irrelevant on PHP 5.4+)

- 'sqlite' database driver (no longer shipped with PHP 5.4+; 'sqlite3' is still available)

- The entire *Encrypt Library* (the newer :doc:`Encryption Library <../libraries/encryption>` is still available; the old Encrypt library depends on MCrypt which was removed from PHP in 7.2)

- The ``$img_path``, ``$img_url`` and ``$font_path`` parameters from
  :doc:`CAPCHA Helper <../helpers/captcha_helper>` function :php:func:`create_captcha()` (pass as array options instead).

Step 11: Make sure you're validating all user inputs
====================================================

The :doc:`Input Library <../libraries/input>` used to (often
unconditionally) filter and/or sanitize user input in the ``$_GET``,
``$_POST`` and ``$_COOKIE`` superglobals.

This was a legacy feature from older times, when things like
`register_globals <https://secure.php.net/register_globals>`_ and
`magic_quotes_gpc <https://secure.php.net/magic_quotes_gpc>`_ existed in
PHP.
It was a necessity back then, but this is no longer the case and reliance
on global filters is a bad practice, giving you a false sense of security.

This functionality is now removed, and so if you've relied on it for
whatever reasons, you should double-check that you are properly validating
all user inputs in your application (as you always should do).

Step 12: Clear your output cache (optional)
===========================================

Internal changes to the :doc:`Output Class <../libraries/output>` make it
so that if you're using the :doc:`Web Page Caching <../general/caching>`
feature, you'll be left with some old, garbage cache files.

That shouldn't be a problem, but you may want to clear them.

Step 13: Remove usage of OCI8 get_cursor() and stored_procedure() methods
=========================================================================

The OCI8 :doc:`Database <database/index>` driver no longer has these two
methods that were specific to it and not present in other database drivers.
The ``$curs_id`` property is also removed.

If you were using those, you can create your own cursors via ``oci_new_cursor()``
and the publicly accessible ``$conn_id``.

Step 14: Check log filename configuration in application/config/config.php
==========================================================================

You can now specify the full log filename via ``$config['log_filename']``.
Add this configuration option to your **application/config/config.php**,
if you haven't copied the new one over.

The ``$config['log_file_extension']`` option still works as a fallback,
but ``$config['log_filename']`` takes precedence when set.
