<?php

class Job extends Eloquent {	
    
    protected $table = 'jobs';
    public $timestamps = false;
    
    static function getOldJobs(){
        
        $job_max_storagetime = Setting::where('name','job_max_storagetime')->first(); // should be in days
        
        $start_date = new DateTime();
        $start_date->sub(new DateInterval('P'.$job_max_storagetime->value.'D'));
        
        $old_jobs = Job::whereNotNull('completed_at')
                    ->where('completed_at','<=',$start_date->format('Y-m-d H:i:s'))
                    ->get();
        
        return $old_jobs;
    }
    
}
    