# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [0.5.1] - 2022-06-28
- Fix locales extraction on `alpine` context

## [0.5.0] - 2022-05-03
- Add ability to preserve tagged data in license headers
- Drop old locales extraction script
- Exit gracefully if external tools not found in plugin release script
- Fix composer handling on Windows env in plugin release script
- Fix handling of `bin/*` files in license headers checks
- Prevent too many empty lines creation in license headers fix

## [0.4.5] - 2022-04-20
- Prevent unsafe git dir exception on `build-package` action

## [0.4.4] - 2022-03-10
- Fix locales extraction from Twig templates

## [0.4.3] - 2022-03-09
- Fix locales extraction when installed using Composer 2+

## [0.4.2] - 2022-01-28
- Fix licence header ending detection

## [0.4.1] - 2021-12-20
- Improve locales extraction
- Improve headers checks
- Fix PSR-12 compliance

## [0.4.0] - 2021-11-17
- Drop `glpi-project/coding-standard` dependency
- Do not check `.gitlab-ci.yml` in licence-headers-check command

## [0.3.1] - 2021-10-28
- Handle *.twig files in license headers check command
- Fix PHP 8.1 compatibility

## [0.3.0] - 2021-10-07
- Latest coding standards

## [0.2.0] - 2021-09-28
- Do not check `css/lib` and `tests/config` in licence-headers-check command
- Handle CSSO preserved comments
- Remove consolidation/robo task runner
- Fix "is not" with literal SyntaxWarning

## [0.1.16] - 2021-03-03
- Enhance license headers check
- Enable usage of Robo 3.x, drop usage of Robo 1.x
- Remove disabled minification tasks

## [0.1.15] - 2020-01-18
- Add licence-headers-check command in replacement of modify_headers.pl script
- Add plugin package building Github action
- Latest coding standards

## [0.1.14] - 2020-10-26
- Permit usage of consolidation/robo 2.x (PHP 7.4 compatibility)
- Call npm install if package.json exists

## [0.1.13] - 2020-06-17
- Fix release build when using --commit option
- Disable minification (bugged)

## [0.1.12] - 2020-04-06
- Fix missing test classes in classmap

## [0.1.11] - 2020-04-03
- Remove vendor useless files

## [0.1.10] - 2020-04-02
- Fix do not check detection

## [0.1.9] - 2020-02-07
- Fix versions order
- Add parametrable exclusions
- Python3 compatible

## [0.1.8] - 2019-06-07
- Fix encoding issues with release script

## [0.1.7] - 2019-05-02
- Add switch to ignore version checking
- Fix help

## [0.1.6] - 2019-03-01
- Fix composer deprecation messages

## [0.1.5] - 2018-06-21
- Generate en_GB po file on strings extraction
- Use stable libraries versions

## [0.1.4] - 2018-02-23
- Latest coding standards

## [0.1.3] - 2018-01-05

- Coding standards are still in 0.5 for projects using tools

## [0.1.3] - 2018-01-02

- Upgrade coding standards to 0.6

## [0.1.2] - 2017-03-03

- Do not check for gh token for mo compilation, version proposal or minify

## [0.1.1] - 2017-02-08

- Fix a bug in standalone minify

## [0.1] - 2017-02-07

First version
