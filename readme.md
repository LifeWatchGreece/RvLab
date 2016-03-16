##Requirements

* Apache 
	rewrite module
* PHP >= 5.4  
  * Mcrypt PHP Extension  
  * cURL PHP Extension  
  * short_tags shound be enabled in php.ini  
* MySQL >= 5.0

##Installation

####Database Schema and configuration

R vLab requires a MySQL database with a schema described in schema.sql file in the documentation directory. This file does not contain only the schema but also a few basic settings needed by R vLab. The credentials that are  used for database connection should be defined in the following file:

`/app/config/database.php`


####File directories

Generally, R vLab uses two separate folders for user files. One for storing jobs and one for storing input files. Each user has its own directory in these two folders. A new directory is created for each job submitted by a user. Assuming that these two folders are:  /.../jobs   and  /.../workspace , the user area file structure will look like:

```
/.../jobs
/.../jobs/user1@gmail.com
/.../jobs/user1@gmail.com/job12
/.../jobs/user1@gmail.com/job17
/.../jobs/user2@gmail.com
/.../jobs/user2@gmail.com/job75
/.../jobs/user2@gmail.com/job76

/.../workspace
/.../workspace/user1@gmail.com
/.../workspace/user1@gmail.com/softLagoon.csv
/.../workspace/user2@gmail.com
/.../workspace/user2@gmail.com/softLagoon.csv
```

, where e.g /.../jobs/user1@gmail.com/job12  is a job-folder. A job-folder is created for each new job and contains all the necessery files for a job to be executed. The two folders mentioned above are designated to reside on a cluster and should get mounted to local directories. So, the web application uses the local paths to read/write, but it also uses the remote paths when building the R scripts (because these scripts will be executed remotely). The local and remote paths are defined in:

`/app/config/rvlab.php`

and an example of installation paths could be:

```
return array(    
    'jobs_path'         	=> '/mnt/cluster/jobs2', 
    'remote_jobs_path'  	=> '/home/rvlab/jobs2', 
    'workspace_path'    	=> '/mnt/cluster/workspace2', 
    'remote_workspace_path'   => '/home/rvlab/workspace2',   
); 
```

If each PHP application is executed under a different user (e.g in case PHP-FPM is used), all application files should be owned by the relevant user. For the same reason, mounting of cluster directories should take place under this user, so that the application can write to the directories.

####Cron jobs

The following three tasks are executed regularly by relevant cron jobs:

`every 2 minutes:`  Update the status of every job that has been submitted and its execution has not finished yet (its status is different than creating, failed or completed). The script that accomplishes that task is located at /app/commands/RefreshStatusCommand.php. 

`every 20 minutes:`  Deletes from the file system and the database, all the jobs that have exceeded the  maximum storage time for R vLab (this time is defined by the parameter job_max_storagetime of settings table). The script that accomplishes that task is located at /app/commands/RemoveOldJobsCommand.php. 

`every 30 minutes:` Checks if the total storage space that is available for users is below the security limit. If not, it deletes job folders from users that exceed their personal storage limit. Τ The script that accomplishes that task is located at /app/commands/StorageUtilizationCommand.php. 

These three tasks should be executed by the same user that R vLab web application is executed and so by the user who is owner of the application files.  


#### License
LifeWatchGreece R vLab is open-sourced software licensed under the MIT license.