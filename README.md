# TurkChan - Lightweight and efficient [imageboard](https://en.wikipedia.org/wiki/Imageboard)
[![Version](https://img.shields.io/badge/version-1.0.0-800000.svg)](inc/config.php)

TurkChan is a rebranded, self-hosted imageboard engine. All branding, logo,
favicon, footer, board naming and version values live in one place:
**`inc/config.php`**.

## Credits & License

TurkChan is derived from [TinyIB](https://codeberg.org/tslocum/tinyib) by Trevor
Slocum, released under the MIT License. The original copyright and license notice
are preserved in `LICENSE` and in the header of `imgboard.php`. The upstream
project is in maintenance mode; see
[Sriracha](https://codeberg.org/tslocum/sriracha) if you need a more modern
imageboard system.

Settings, board behaviour and database layout still use the upstream `TINYIB_*`
constants and `$tinyib_*` arrays so that existing boards, databases and
translations keep working unchanged. Only the **brand identity** is TurkChan.

## Features

**Got database? Get speed.**  Use [MySQL](https://mysql.com), [PostgreSQL](https://www.postgresql.org) or [SQLite](https://sqlite.org) for an efficient set-up able to handle high amounts of traffic.

**No database?  No problem.**  Store posts as text files for a portable set-up capable of running on virtually any PHP host.

**Not looking for an image board script?**  TurkChan is able to allow new threads without requiring an image, or disallow images entirely.

 - GIF, JPG, PNG, SWF, MP4 and WebM upload.
 - YouTube, Vimeo and SoundCloud embedding.
 - CAPTCHA:
   - A simple, self-hosted implementation is included.
   - [hCaptcha](https://hcaptcha.com) is supported.
   - [ReCAPTCHA](https://www.google.com/recaptcha/about/) is supported. (But [not recommended](https://nearcyan.com/you-probably-dont-need-recaptcha/))
 - Reference links. `>>###`
 - Fetch new replies automatically. (See `TINYIB_AUTOREFRESH`)
 - Delete posts via password.
 - Report posts.
 - Block keywords.
 - Management panel:
   - Account system:
     - Super administrators (all privileges)
     - Administrators (all privileges except account management)
     - Moderators (only able to sticky threads, lock threads, approve posts and delete posts)
   - Ban offensive/abusive posters across all boards.
   - Post using raw HTML.
   - Upgrade automatically when installed via git.  (Tested on Linux only)
 - [Translations:](https://translate.codeberg.org/projects/tinyib/tinyib/)
   - Catalan, Chinese, Dutch, Finnish, French, German, Indonesian, Italian, Japanese, Korean, Norwegian, Polish, Portuguese, Romanian, Russian, Spanish (Mexico) and Turkish

## Brand configuration

Everything brand-related is centralised in **`inc/config.php`**:

| Constant | Purpose |
| --- | --- |
| `TURKCHAN_VERSION` (+ `_MAJOR`/`_MINOR`/`_PATCH`) | Version system; shown on the management panel Status page |
| `TURKCHAN_NAME`, `TURKCHAN_SLUG` | Brand name and its lowercase code form |
| `TURKCHAN_LOGO_TEXT` / `_HTML` / `_IMAGE` / `_SUBTITLE` | Logo system |
| `TURKCHAN_FAVICON` | Favicon path |
| `TURKCHAN_BOARD_PREFIX` / `_INDEX` / `_CATALOG` / `_CATALOG_JSON` / `_THREADS_JSON` | Board naming system |
| `TURKCHAN_JS`, `TURKCHAN_LOCKFILE`, `TURKCHAN_FORM_NAME` | Asset filenames and form identity |
| `TURKCHAN_PROJECT_URL`, `_REPO_URL`, `_ISSUES_URL`, `_COMMITS_URL`, `_README_URL`, `_UPSTREAM_URL` | Project links |
| `TURKCHAN_FOOTER_HTML`, `TURKCHAN_FOOTER_DEFAULT` | Footer/header structure |

Per-board settings (board ID, description, title, uploads, database, limits)
stay in **`settings.php`** using the `TINYIB_*` constants and `$tinyib_*` arrays.

## Donate

Donations for the upstream project that TurkChan is derived from:

- [LiberaPay](https://liberapay.com/rocket9labs.com) (anonymous, no added fees)
- [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=TEP9HT98XK7QA)

## Install

 1. Verify the following are installed:
    - [PHP 5.5+](https://php.net)
    - [GD Image Processing Library](https://php.net/gd)
      - This library is usually installed by default.
      - If you plan on disabling image uploads to use TurkChan as a text board only, this library is not required.
     - [cURL Library](https://www.php.net/manual/en/book.curl.php)
       - This is recommended, but is not strictly required except when `TINYIB_CAPTCHA` is set to `hcaptcha` or `recaptcha`.
 2. CD to the directory you wish to install TurkChan.
 3. Run the command:
    - `git clone https://github.com/rainkawa/turkChan.git ./`
 4. Copy **settings.default.php** to **settings.php**
 5. Configure **settings.php**
    - When setting ``TINYIB_DBMODE`` to ``flatfile``, note that all post, report and ban data are exposed as the database is composed of standard text files.  Access to ./inc/database/flatfile/ should be denied.
    - When setting ``TINYIB_DBMODE`` to ``pdo``, note that only the MySQL and PostgreSQL databases drivers have been tested. Theoretically it will work with any applicable driver, but this is not guaranteed.  If you use an alternative driver, please report back.
    - Field length settings require a modification to the database field to accommodate the increased length in order to take effect.
    - To require moderation before displaying posts:
      - Set ``TINYIB_REQMOD`` to ``files`` to require moderation for posts with files attached.
      - Set ``TINYIB_REQMOD`` to ``all`` to require moderation for all posts.
      - Moderate posts by visiting the management panel.
    - To allow video uploads:
      - Ensure your web host is running Linux.
      - Install [ffmpeg](https://ffmpeg.org).  On Ubuntu, run ``sudo apt-get install ffmpeg``.
      - Add desired video file types to ``$tinyib_uploads``.
    - To remove the play icon from .SWF and .WebM thumbnails, delete or rename `video_overlay.png`.
    - To use FFMPEG to create thumbnails:
        - Install FFMPEG and ensure  the ``ffmpeg`` and ``ffprobe`` commands are available.
        - Set ``TINYIB_THUMBNAIL`` to ``ffmpeg``.
    - To use ImageMagick instead of GD when creating thumbnails:
      - Install ImageMagick and ensure that the ``convert`` command is available.
      - Set ``TINYIB_THUMBNAIL`` to ``imagemagick``.
      - **Note:** GIF files will have animated thumbnails, which will often have large file sizes.
    - To use TurkChan in another language, set ``TINYIB_LOCALE`` to a language code found in `locale/`.
      - **Note:** The [mbstring](https://www.php.net/manual/en/book.mbstring.php) PHP extension must be installed and enabled for TurkChan to properly support operating on and rendering text in any language other than English.
 6. [CHMOD](https://en.wikipedia.org/wiki/Chmod) write permissions to these directories:
    - ./ (the directory containing TurkChan)
    - ./src/
    - ./thumb/
    - ./res/
    - ./inc/database/flatfile/ (only if you use the ``flatfile`` database mode)
 7. Navigate your browser to **imgboard.php** and the following will take place:
    - The database structure will be created.
    - Directories will be verified to be writable.
    - The board index will be written to ``TINYIB_INDEX``.

## Moderate

 1. If you are not logged in already, log in to the management panel by clicking **[Manage]**.
 2. On the board, tick the checkbox next to one or more offending posts.
 3. Scroll to the bottom of the page.
 4. Click **Delete**.
    - You will be redirected to the management panel.
    - From this page you are able to delete the post(s) and/or ban the author(s).

## Update

 1. Obtain the latest release.
    - If you installed via Git, run the following command in TurkChan's directory:
      - `git pull`
    - Otherwise, [download](https://github.com/rainkawa/turkChan/archive/refs/heads/main.zip) and extract a zipped archive.
 2. Note which files were modified.
    - If **settings.default.php** was updated, migrate the changes to **settings.php**
      - Take care to not change the value of `TINYIB_TRIPSEED`, as it is used to generate secure tripcodes, hash passwords and hash IP addresses.
    - If other files were updated, and you have made changes yourself:
      - Visit [github.com](https://github.com/rainkawa/turkChan) and review the changes made in the update.
      - Ensure the update does not interfere with your changes.

## Migrate

TurkChan includes a database migration tool.

While the migration is in progress, visitors will not be able to create or delete posts.

 1. Edit **settings.php**
    - Set ``TINYIB_DBMIGRATE`` to the desired ``TINYIB_DBMODE`` after the migration.
    - Configure all settings related to the desired ``TINYIB_DBMODE``.
 2. Open the management panel.
 3. Click **Migrate Database**
 4. Click **Start the migration**
 5. If the migration was successful:
    - Edit **settings.php**
      - Set ``TINYIB_DBMODE`` to the mode previously specified as ``TINYIB_DBMIGRATE``.
      - Set ``TINYIB_DBMIGRATE`` to a blank string (``''``).
    - Click **Rebuild All** and ensure the board still looks the way it should.

## Support

 1. Ensure you are running the latest version of TurkChan.
 2. Review the [open issues](https://github.com/rainkawa/turkChan/issues).
 3. Open a [new issue](https://github.com/rainkawa/turkChan/issues/new).

## Translate

Translation is handled [online](https://translate.codeberg.org/projects/tinyib/tinyib/).

## Contribute

**Note:** Please do not submit translations via pull requests.  See above.

 1. [Fork TurkChan.](https://github.com/rainkawa/turkChan/fork)
 2. Commit code changes to your forked repository.
 3. [Submit a pull request.](https://github.com/rainkawa/turkChan/pulls)
