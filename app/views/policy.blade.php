<?php
    $user_soft_limit = $rvlab_storage_limit / $max_users_suported; // in KB    
    
    if($user_soft_limit > 1000000)
        $soft_limit = number_format($user_soft_limit/1000000,2)." GB";
    elseif($user_soft_limit > 1000)
        $soft_limit = number_format($user_soft_limit/1000,2)." MB";
    else
        $soft_limit = number_format($user_soft_limit,2)." KB";
?>

<div style="font-weight: bold; font-size: 18px; text-align: center; margin: 20px">R vLab Storage Policy</div>

<p>
    1. A job is automatically deleted after {{ $job_max_storagetime }} days.
</p>
<p>
   2. Each user has available at least {{ $soft_limit }} to store his/her input files or the results of submitted jobs. 
   This will be called "soft limit". More space can be used by users but in case R vLab runs out of storage, jobs 
   will be deleted from users that have exceeded the "soft limit" until the get below the soft-limit. In such a case,
   the job deletion will be done from the oldest job to the newest.
</p>   
<p>
    3. Each user can check the utilization of his storage space in the "Workspace File Management" tab. The
    utilization is calculated in accordance to the soft-limit defined above.
</p>

<div style="font-weight: bold; font-size: 18px; text-align: center; margin: 20px">R vLab Usage Policy</div>

<p>
    1. The first time you are going to visit R vLab you will be asked to register as an R vLab user for 
    a certain period. You have to select among fixed choices like "1 day", "1 week", "1 month" etc. Please, 
    select the one that better respresents your intentions. For example, if your are just visiting R vLab 
    to check out what this lab is about, then select "1 day" or "1 week". This registration is vital in order 
    to be able to provide a minimum quality of service to every R vLab user. When the period that you have
    registered for expires, the registration page will appear again to you.
</p>
<p>
    2. A user's jobs are not deleted automatically when a his registration period expires. The deletion of a job
    follows the rules that are mentioned above and is not depended to the registration expiration.
</p>
