# PHP session manager implementing security against hijacking

Install with `composer require seba1rx/sessionadmin`

The SessionAdmin class has 3 public methods: `activateSession()`,  `createUserSession()` and `terminate()`.

The SessionAdmin class is fully documented so you can check each method or property in order to get to understand it better.

### The SessionAdmin class is defined as an abstract class but has no abstract methods, it is intended to be extended by implementing a custom constructor.

## There are 2 demos
* MPA (Multi Page Application)
* SPA (Single Page Application)

This class allows you to easily set up a secure session and have session data for guests and authenticated users.

Features:
- Creates a session for guest and users
- Named session
- 3% chances of regenerating session id on each request to prevent session fixation
- Prevents hijacking
- session destruction on obsolete request
- proxy-aware ip detection
- Optional in MPA: Define allowed URL array for guests, that can be expanded when user logs in according to system profile


### On each demo you will find more info about each implementation