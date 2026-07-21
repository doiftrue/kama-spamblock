# Kama SpamBlock

Kama SpamBlock is a WordPress plugin that blocks basic automated comment spam.

## Development

- Support PHP 7.4 and later.
- Follow the existing WordPress-oriented PHP style in the project.
- Install development dependencies with `make composer.install`.

## Tests

- Run the unit tests with `make phpunit`; add or update tests for behavior changes.
- Test suite filenames must end with `__Test.php`; test method names must start with `test__`.
- The PHPUnit bootstrap is `tests/unit/bootstrap.php`.
- Tests use `doiftrue/unitest-wp-copy` to run selected real, pure-PHP WordPress functions and classes without bootstrapping full WordPress, a database, or external services. Prefer these real implementations over mocking their behavior.
- Check `vendor/doiftrue/unitest-wp-copy/SYMBOLS-INFO.md` to see which WordPress symbols are available and mockable.
- The runtime keeps WordPress-like globals and option stores in the PHP process. Restore any state changed by a test in `setUp()` or `tearDown()`. Change configured options through `$GLOBALS['stub_wp_options']`; WP Mock cannot override an option already present there.
- Tests also use `10up/wp_mock`. Use `WP_Mock` for WordPress functions that need mocked return values, expected calls, hooks, or behavior not supplied by `unitest-wp-copy`; extend `WP_Mock\Tools\TestCase` when per-test WP Mock setup and teardown are needed.
