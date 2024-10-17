# Changelog

## [Unreleased]

## [1.2.0] - 2024-10-17

* [PR-21](https://github.com/ITK-Leantime/leantime-timetable/pull/21)
  * Add functionality and translation to button provided in #19
* [PR-19](https://github.com/ITK-Leantime/leantime-timetable/pull/19)
  * Update css and finetune design

## [1.1.0] - 2024-09-23

* [PR-20](https://github.com/ITK-Leantime/leantime-timetable/pull/20)
  * Sync missing ticket on open

* [PR-17](https://github.com/ITK-Leantime/leantime-timetable/pull/17)
  * Added delete button to timelog modal
  * Enable change date when editing time log

* [PR-16](https://github.com/ITK-Leantime/leantime-timetable/pull/16)
  * Assets are now versioned with query strings to [cache bust](https://www.keycdn.com/support/what-is-cache-busting#1-file-name-versioning)
  * Better preset values when logging time

## [1.0.3] - 2024-09-19

* [PR-13](https://github.com/ITK-Leantime/leantime-timetable/pull/14)
  Remove urlencode in `register.php`

## [1.0.2] - 2024-09-19

## [1.0.1] - 2024-09-19

* [PR-12](https://github.com/ITK-Leantime/leantime-timetable/pull/12)
  Hotfix: Fix version placeholder in asset

## [1.0.0] - 2024-09-18

## [0.0.1] 2024-10-13

* [PR-9](https://github.com/ITK-Leantime/leantime-timetable/pull/10)
  * Run GA with markdown runner
  * Update create-release script
  * Add GA that checks if documentation has been updated

* [PR-8](https://github.com/ITK-Leantime/leantime-timetable/pull/8)
  * Added compatability for Leantime 3.2
  * Consistent naming and error handling

* [PR-7](https://github.com/ITK-Leantime/leantime-timetable/pull/7)
  * Added settings page with input for ticket cache timeout
  * Grabbing cache timeout value in controller and passing to template

* [PR-6](https://github.com/ITK-Leantime/leantime-timetable/pull/6)
  * Added styling throughout plugin
  * Rewritten timeTable.js into a class, overhauled methods and added doccomments
  * Added separate apihandler used by timetable.js
  * Modified blade template to better match design
  * Loading apihandler frontend
  * Added apihandler symlink to install script
  * Added additional data as well as a get hook for jQuery ajax
  * Added left join to project table in order to get project name with worklogs
  * Streamlined design with figma

* [PR-4](https://github.com/ITK-Leantime/leantime-timetable/pull/4)
  * Adds get/posts in the controller. post for saving, get for getting/sorting/organizing data
  * sql in repository, as the sql in the code base did not cover our use case
  * Create a basic table in blade-file

* [PR-3](https://github.com/ITK-Leantime/leantime-timetable/pull/5)
  * Added build release script
  * Added deploy script
  * Add `%%VERSION%%`, that is replaced by create-release script, to make [leantime load new script/css files](https://www.keycdn.com/support/what-is-cache-busting)
  * Add `release.yml`, that builds both release and pre-releases on push tag/branch
  * Add shellcheck to `pr.yml`

* [PR-2](https://github.com/ITK-Leantime/leantime-timetable/pull/2)
  * Setup code analysis
  * Setup translations
  * Setup github actions
  * Setup linting
  * Create a changelog

* [PR-1](https://github.com/ITK-Leantime/leantime-timetable/pull/1)
  * Foundation for further development

[Unreleased]: https://github.com/ITK-Leantime/leantime-timetable/compare/1.2.0...HEAD
[1.2.0]: https://github.com/ITK-Leantime/leantime-timetable/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/ITK-Leantime/leantime-timetable/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/ITK-Leantime/leantime-timetable/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/ITK-Leantime/leantime-timetable/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/ITK-Leantime/leantime-timetable/compare/0.0.1...1.0.0
[0.0.1]: https://github.com/ITK-Leantime/leantime-timetable/releases/tag/0.0.1
