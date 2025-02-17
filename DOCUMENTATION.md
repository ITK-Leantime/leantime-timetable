# Leantime TimeTable

The purpose of this plugin is to ease the task of logging time on a day-to-day basis.

## Controller

This plugin primarily consists of a Controller, getting its data from the database via a custom repository called through
the service. The custom repository exists because of the need for custom datasets not currently supported by the API.

A handful of get-parameters can be provided to the controller to modify the data shown:

- `fromDate`: Specifies the starting date for the timetable data. Format: `YYYY-MM-DD` or a `DateTime::modify` modifier
  (e.g., `-1 day`).
- `toDate`: Specifies the ending date for the timetable data. Format: `YYYY-MM-DD` or a `DateTime::modify` modifier
  (e.g., `+1 week`).
- `manageAsUserId`: Specifies the `userId` whose timetable is to be managed. Only admin or above can utilize this.

Furthermore, the `DateTime::modify` functionality is implemented, allowing a modifier to be passed instead of a specific
date. This modifies the perceived date by applying an offset to today's date, enabling the creation of dynamic URLs.

## Template

The controller finally renders the `timetable.blade.php` template file, while passing the relevant data for the template
to render the table. From there, posts through forms are used to handle the different actions, posted back to the
controller.

## Javascript

The JavaScript consists of two major parts. One is the actual controller class, and the other is for communicating with
the API asynchronously.

As an attempt to reduce loading time and improve the general user experience, all to-dos are synced into `localStorage`.
This ensures almost instant searchability, the drawback being the need to sync the data once in a while.
