# phpInit  
Initialize a PHP page controller app with important PHP settings and a custom error handler.  

## Custom Error Handler  
Handles as many PHP errors as possible.  
1. Display  
- Does not display errors on production server.  
- Optionally displays errors on test servers.  

2. Log  
- Logs to file configured in .env.  

3. Email  
Coming Soon. Will use PHPMailer and limit # of emails per hour (I learned this the hard way, as errors on every page load caused my server to bog down with thousands of email sends).  
