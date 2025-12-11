# pageflow  
Pageflow initializes a PHP page controller app with important PHP settings, a custom error handler, optional PHPMailer access, optional PostGreSQL database connection (PHP pgsql extension required).  

## Requirements  
PHP 7.1+

## Installation & Usage  
```
$ composer require pageflow/pageflow
```
Copy .env.example to .env, and edit the settings.  

Add the following code to the top of your php file(s):  
```
use Pageflow\Pageflow;

require __DIR__ . '/../vendor/autoload.php';

$pageflow = Pageflow::getInstance();
$pageflow->start();
```

## Why Page Controller?  
Small and medium sized web apps may not need the overhead of front controlled [frameworks](https://toys.lerdorf.com/the-no-framework-php-mvc-framework). Request routing in front controllers adds a layer of abstraction and complexity that can be eliminated by a simple page controller model, with only 1 required file at the top of each page to provide configuration and access to commonly used features.  

## Custom Error Handler  
Handles as many PHP errors and uncaught exceptions as possible. Provides a stack trace to help debug.  
1. Display  
- Does not display errors on production server.  
- Optionally displays errors on test servers.  

2. Log  
- Logs to file configured in .env.  

3. Email  
- Emails errors to webmaster configured in .env.  
- Only emails if less than 10 emails have been sent in the past hour (otherwise emails on every page load can cause server slowdown).  

## PHPMailer  
Set appropriate .env vars to instantiate a helpful service layer object called $emailer. Then access with $emailer->getPhpMailer() or just email using $emailer->send().  

## PostGreSQL Database  
Set the connection string var in .env to instantiate a helpful service layer object called $postgres, which includes a Query Builder. Use that to run queries, or the connection constant PG_CONN to query using native PHP pg functions.  