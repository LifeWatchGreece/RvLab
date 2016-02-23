<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Enforces the RvLab usage policy by deleting expired jobs
 *
 * @license MIT
 * @author Alexandros Gougousis
 */
class RemoveOldJobsCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'command:removeOldJobs';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
            $jobs_path = Config::get('rvlab.jobs_path');
            $old_jobs = Job::getOldJobs();
            $counter = 0;
            
            foreach($old_jobs as $job){
                
                try {                    
                    // Delete job files
                    $job_folder = $jobs_path.'/'.$job->user_email.'/job'.$job->id;
                    if(!delete_folder($job_folder)){
                        $this->save_log('Folder '.$job_folder.' could not be deleted!',"error");                                        
                    }

                    // Delete job record
                    $job->delete();
                    
                    $counter++;
                    
                } catch (Exception $ex) {
                    $this->save_log("Error occured during deletion of job".$job->id.". Message: ".$ex->getMessage(),"error");                   
                }                                
            }            
            //$this->save_log($counter." out of ".count($old_jobs)." old files were deleted.","info");      

	}
        
        private function save_log($message,$category){                    

            $log = new SystemLog();
            $log->when 	=   date("Y-m-d H:i:s");
            $log->user_email =   'system';
            $log->controller =  'Laravel Command';
            $log->method 	=   'RemoveOldJobsCommand';
            $log->message 	=   $message;
            $log->category   =   $category;
            $log->save();
            
        }

}
