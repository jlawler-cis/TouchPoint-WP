# TouchPoint WP
A WordPress Plugin for integrating with [TouchPoint Church Management Software](https://github.com/bvcms/bvcms).

Developed by [Tenth Presbyterian Church](https://tenth.org) for their website and released under the AGPL License. This
plugin is not developed or supported by TouchPoint.  While their support team is stellar, they probably won't be able to
help you with this.

[See the Wiki for Installation instructions and other documentation.](https://github.com/TenthPres/TouchPoint-WP/wiki)

## Features
### Involvement Lists & Small Group Finder
- Publish a list and map of involvements, with dynamic filtering based on actual data, such as demographics and location.
- Suggest Small Groups physically nearby based on geolocation.
- Allow website guests to easily contact leaders or join a group, without exposing leaders' contact info publicly.

[Small Groups Example.](https://www.tenth.org/smallgroups)
[Classes Example.](https://www.tenth.org/abs)

### Crazy-Simple RSVP interface
Let folks RSVP for an event for each member in their family in just a few clicks.
No login required, just an email address and zip code.

### People Lists
Show your Staff members, Elders, or other collections of people, automatically kept in sync with TouchPoint.
[Example.](https://www.tenth.org/about/staff)

### Outreach Partners
Automatically import partner bios and info can be imported from TouchPoint for display on your public website, with 
appropriate care for their security.
[Example.](https://www.tenth.org/outreach/partners)

### Events
Improve display of events in the TouchPoint Custom Mobile App by providing content from [The Events Calendar Plugin by
ModernTribe](https://theeventscalendar.com/).  This is compatible with both the free and "Pro" versions.

<!--
### Authentication (Beta)
Authenticate TouchPoint users to WordPress, so you can know your website users.  Optionally, this authentication can
happen silently in the background, so that if a user is logged into TouchPoint, they are automatically logged into your
website.
-->

## Future Features
- Authenticate
    - Unify your public web properties with a single login. 
    - Track viewership of webpages and web resources non-anonymously.  (Know who attended your virtual worship service.)
    - Sync WordPress Permissions with TouchPoint involvements or roles.
- Events (Requires [The Events Calendar from ModernTribe](https://theeventscalendar.com/))
    - Link TouchPoint Meetings with Calendar events (Requires Pro version of The Events Calendar)
- Small Groups
    - Suggest demographically-targeted small groups.
- Bios & Contact Info
    - Generate bio pages for officers or staff members from TouchPoint People records.
    

## Requirements

Making this work requires notable configuration of your TouchPoint database.  We've scripted what we can, and the
remainder is in [the Installation Instructions](https://github.com/TenthPres/TouchPoint-WP/wiki/Installation).

Some features require other plugins, which may or may not be free.

You will need a TouchPoint user account with API-level access.  New TouchPoint databases do not have one by default.  
If your church doesn't have one, open a support ticket with TouchPoint to create one, referencing this plugin.

If you're using the Authentication component, your WordPress site **MUST** use HTTPS with a valid certificate.

We don't promise support for old versions of WordPress or PHP.  You will need to keep both up to date.

## Multisite Support

At the moment, this plugin won't perform very well in a multisite environment.  We're working on that, though, as we 
plan on moving our own infrastructure toward multisite soon.  As currently planned, ALL sites in a multisite network 
will share ONE TouchPoint connection, and many (though not all) of the settings would be shared across the network.  If 
you're interested in using this plugin in a multisite environment, [please get in touch](mailto:jkurtz@tenth.org). 

## Credit & Hat-Tips

This plugin uses PSR-12 coding standards, which are significantly different from the WordPress standards, but are easier
for working in [OOP](https://en.wikipedia.org/wiki/Object-oriented_programming).  This plugin heavily uses OOP.

Several plugins have lended structure or code snippets to this plugin:
- [Plugin Template from hlashbrooke](https://github.com/hlashbrooke/WordPress-Plugin-Template) (GPLv2)
- [AAD SSO from psignoret](https://github.com/psignoret/aad-sso-wordpress) (MIT)

### Other Software Used Within this Software
- [SweetAlert2](https://sweetalert2.github.io/) (MIT)
- [Knockout JS](https://knockoutjs.com/) (MIT)
- [Google Maps Javascript API](https://developers.google.com/maps/documentation/javascript/overview) 
  ([Proprietary](https://developers.google.com/terms))
- [FontAwesome](https://fontawesome.com/) ([SIL OFL 1.1](http://scripts.sil.org/OFL))

### License
This plugin is released under the AGPL, which is "very strong copy-left".  Therefore, if you change this code and use it
in production, you *MUST* make your changes available.