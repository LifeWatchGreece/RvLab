<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Checks out if storage is running out of free space 
 *
 * @license MIT
 * @author Alexandros Gougousis
 */
class StorageUtilizationCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'command:storageUtilization';

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
            // Calculate storage utilization           
            $rvlab_storage_limit = Setting::where('name','rvlab_storage_limit')->first(); 
            
            $jobs_path = Config::get('rvlab.jobs_path');
            $jobs_size = directory_size($jobs_path); // in KB

            $workspace_path = Config::get('rvlab.workspace_path');
            $workspace_size = directory_size($workspace_path); // in KB
            
            $used_size = $jobs_size+$workspace_size; 
            $utilization = 100*$used_size/$rvlab_storage_limit->value;            
                        
            // If we are running out of space, delete jobs from users that have
            // exceeded their personal limit
            $max_users_suported = Setting::where('name','max_users_suported')->first();   
            $user_soft_limit = $rvlab_storage_limit->value/$max_users_suported->value;
            
            if($utilization > 10){
                // Find R vLab active users
                $users_with_inputs = WorkspaceFile::select('user_email')->distinct()->get()->toArray(); // Get users with a least one input file
                $users_with_jobs = Job::select('user_email')->distinct()->get()->toArray(); // Get users with a least one job
                $iu = flatten($users_with_inputs);
                $ju = flatten($users_with_jobs);
                $active_users = array_unique(array_merge($iu,$ju));
                               
                foreach($active_users as $user_email){
                    $jobs_size = directory_size($jobs_path.'/'.$user_email); // in KB
                    $workspace_size = directory_size($workspace_path.'/'.$user_email); // in KB
                    // If user has exceeded his soft limit
                    if(($jobs_size+$workspace_size)>$user_soft_limit){
                        // Get user's jobs
                        $jobs = Job::where('user_email',$user_email)->whereIn('status',array('completed','failed'))->orderBy('submitted_at','asc')->get();                        
                        // Delete jobs until user does not exceed his soft limit
                        // (delete jobs from oldest to newer)
                        foreach($jobs as $job){  
                            // Delete the job
                            try {
                                $job_id = $job->id;
                                
                                // Delete job record
                                $job->delete();

                                // Delete job files
                                $job_folder = $jobs_path.'/'.$user_email.'/job'.$job_id;
                                if(!delete_folder($job_folder)){
                                    $this->save_log('Folder '.$job_folder.' could not be deleted!',"error");                                                
                                }
                                $this->save_log('Folder deleted - Job ID: '.$job_id.' - User: '.$user_email,"info");
                            } catch (Exception $ex) {
                                $this->save_log("Error occured during deletion of job".$job_id.". Message: ".$ex->getMessage(),"error");                                
                            }
                            
                            // Check if user still exceeds its soft limit
                            $new_jobs_size = directory_size($jobs_path.'/'.$user_email); // in KB
                            if(($new_jobs_size+$workspace_size)<=$user_soft_limit){
                                break;
                            }
                        }
                    } 
                }
                
            }
            
	}
        
        private function save_log($message,$category){                    

            $log = new SystemLog();
            $log->when 	=   date("Y-m-d H:i:s");
            $log->user_email =   'system';
            $log->controller =  'Laravel Command';
            $log->method 	=   'StorageUtilizationCommand';
            $log->message 	=   $message;
            $log->category   =   $category;
            $log->save();
            
        }

}
