<?php

/**
 * Implements functionality related to job submission, job status refreshing and building the results page.
 *
 * @license MIT
 * @author Alexandros Gougousis
 * @author Anastasis Oulas
 */
class JobController extends AuthController {

    private $workspace_path;
    private $jobs_path; 
    private $remote_jobs_path;
    private $remote_workspace_path;    
    
    public function __construct() {
        parent::__construct();
        $this->workspace_path = Config::get('rvlab.workspace_path');
        $this->jobs_path = Config::get('rvlab.jobs_path');
        $this->remote_jobs_path = Config::get('rvlab.remote_jobs_path');
        $this->remote_workspace_path = Config::get('rvlab.remote_workspace_path');                                                          
        
        // Check if cluster storage has been mounted to web server
        if(!$this->check_storage()){          
            if($this->is_mobile){          
                $response = array('message','Storage not found');
                return Response::json($response,500);
                die();
            } else {
                echo $this->load_view('errors/unmounted','Storage not found');
                die();
            }               
        }
        
        $this->check_registration();        
    }        
    
    /**
     * 
     * Deletes jobs selected by user
     * 
     * @return RedirectResponse
     */
    public function delete_many_jobs(){
        
        $form = Input::all();
        
        if(!empty($form['jobs_for_deletion'])){
            $job_list_string = $form['jobs_for_deletion'];
            $job_list = explode(';',$job_list_string);

            $total_success = true;
            $error_messages = array();
            $count_deleted = 0;

            foreach($job_list  as $job_id){
                $result = $this->delete_one_job($job_id);
                if($result['deleted']){
                    $count_deleted++;
                } else {
                    $total_success = false;
                    $error_messages[] = $result['message'];
                }
            }

            $deletion_info = array(
                'total'     =>  count($job_list),
                'deleted'   =>  $count_deleted,
                'messages'  =>  $error_messages
            );        

            if($this->is_mobile){            
                return Response::json($deletion_info,200);
            } else {
                return Redirect::to('/')->with('deletion_info',$deletion_info);
            }
        } else {
            return Redirect::to('/');
        }
                          
    }
    
    /**
     * Deletes a specific job
     * 
     * @param int $job_id
     * @return array
     */
    private function delete_one_job($job_id){
        
        $job = Job::find($job_id);
        $user_email = $this->user_status['email'];  
        
        // Check if this job exists
        if(empty($job)){
            $this->log_event("User tried to delete a job that does not exist.","illegal");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job ('.$job_id.') that does not exist'
            );            
        }
        
        // Check if this job belongs to this user
        if($job->user_email != $user_email){
            $this->log_event("User tried to delete a job that does not belong to him.","unauthorized");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job that does not belong to you.'
            ); 
        }        
        
        // Check if the job has finished running
        if(in_array($job->status,array('running','queued','submitted'))){
            $this->log_event("User tried to delete a job that is not finished.","illegal");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job ('.$job_id.') that is not finished.'
            );  
        }        
        
        try {
            // Delete job record
            Job::where('id',$job_id)->delete();

            // Delete job files
            $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
            if(!delete_folder($job_folder)){
                $this->log_event('Folder '.$job_folder.' could not be deleted!',"error");
                return array(
                    'deleted'   =>  false,
                    'message'   =>  'Unexpected error occured during job folder deletion ('.$job_id.').'
                );                   
            }
            
            return array(
                'deleted'   =>  true,
                'message'   =>  ''
            ); 
        } catch (Exception $ex) {
            $this->log_event("Error occured during deletion of job".$job_id.". Message: ".$ex->getMessage(),"error");
            return array(
                'deleted'   =>  false,
                'message'   =>  'Unexpected error occured during deletion of a job ('.$job_id.').'
            ); 
        }
                        
    }
    
    /**
     * Retrieves the list of jobs in user's workspace
     * 
     * @return JSON
     */
    public function get_user_jobs(){
        if(!empty($this->user_status)){
            $user_email = $this->user_status['email']; 
            $timezone = $this->user_status['timezone']; 
            $job_list = Job::where('user_email',$user_email)->orderBy('id','desc')->get();
            foreach($job_list as $job){
                $job->submitted_at = dateToTimezone($job->submitted_at, $timezone);
                $job->started_at = dateToTimezone($job->started_at, $timezone);
                $job->completed_at = dateToTimezone($job->completed_at, $timezone);
            }
            $json_list = $job_list->toJson();
            return Response::json($json_list,200);
        } else {
            return Response::json(array('message'=>'You are not logged in or your session has expired!'),401);
        }               
    }
    
    /**
     * Retrieves the status of a submitted job
     * 
     * @param int $job_id
     * @return JSON
     */
    public function get_job_status($job_id){
        
        $user_email = $this->user_status['email']; 
        
        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();
        
        if(empty($result)){
            $this->log_event("Trying to retrieve status for a job that does not belong to this user.","unauthorized");
            $response = array('message','Trying to retrieve status for a job that does not belong to this user!');
            return Response::json($response,401);
        }
        
        return Response::json(array('status' => $result->status),200);
        
    }
    
    /**
     * Retrieves the R script used in the execution of a submitted job.
     * 
     * @param int $job_id
     * @return JSON
     */
    public function get_r_script($job_id){
        $user_email = $this->user_status['email']; 
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $fullpath = $job_folder.'/job'.$job_id.'.R';
        
        // Check if the R script exists
        if(!file_exists($fullpath)){
            $this->log_event("Trying to retrieve non existent R script.","illegal");
            $response = array('message','Trying to retrieve non existent R script!');
            return Response::json($response,400);
        }
        
        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();
        
        if(empty($result)){
            $this->log_event("Trying to retrieve an R script from a job that does not belong to this user.","unauthorized");
            $response = array('message','Trying to retrieve an R script from a job that does not belong to this user!');
            return Response::json($response,401);
        }
        
        $r = file($fullpath);
        return Response::json($r,200);
        
        
    }
    
    /**
     * Retrieves a file from a job's folder.
     * 
     * @param int $job_id
     * @param string $filename
     * @return View|file|JSON
     */
    public function get_job_file($job_id,$filename){
        
        $user_email = $this->user_status['email']; 
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $fullpath = $job_folder.'/'.$filename;

        if(!file_exists($fullpath)){
            $this->log_event("Trying to retrieve non existent file.","illegal");
            if($this->is_mobile){
                $response = array('message','Trying to retrieve non existent file!');
                return Response::json($response,400);
            } else {
                return $this->illegalAction();
            }              
        }
        
        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();
        
        if(!empty($result)){              
            $parts = pathinfo($filename);
            $new_filename = $parts['filename'].'_job'.$job_id.'.'.$parts['extension'];
                         
            switch($parts['extension']){
                case 'png':                    
                    //$this->log_event("Requesting a png file.","info");
                    //header("Content-Disposition: filename=$filename");
                    header("Content-Type: image/png");
                    //header('Content-Length: ' . filesize($fullpath));
                    readfile($fullpath);                         
                    exit;
                    break;
                case 'csv':
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.$new_filename);
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($fullpath));
                    readfile($fullpath);
                    exit;
                    break;
            }
            
        } else {
            $this->log_event("Trying to retrieve a file that does not belong to a user's job.","unauthorized");
            if($this->is_mobile){
                $response = array('message',"Trying to retrieve a file that does not belong to a user's job");
                return Response::json($response,401);
            } else {
                return $this->unauthorizedAccess();
            }               
        }  
        
    }
    
    /**
     * 
     * @param int $job_idRefreshes the status of a specific job
     * 
     * @param int $job_id
     * @return void
     */
    private function refresh_single_status($job_id){
        $job = Job::find($job_id);
        
        $job_folder = $this->jobs_path.'/'.$job->user_email.'/job'.$job_id;
        $pbs_filepath = $job_folder.'/job'.$job->id.'.pbs';
        $submitted_filepath = $job_folder.'/job'.$job->id.'.submitted';
            
        if(file_exists($pbs_filepath)){
            $status = 'submitted';
        } else if(!file_exists($submitted_filepath)){
            $status = 'creating';
        } else {
            $status_file = $job_folder.'/job'.$job_id.'.jobstatus';
            $status_info = file($status_file);
            $status_parts = preg_split('/\s+/', $status_info[0]); 
            $status_message = $status_parts[8];
            switch($status_message){
                case 'Q':
                    $status = 'queued';
                    break;
                case 'R':
                    $status = 'running';
                    break;
                case 'ended':
                    $status = 'completed';
                    break;
                case 'ended_PBS_ERROR':
                    $status = 'failed';
                    break;
            }

            switch($job->function){
                case 'bict':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_postgres_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_anosim':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_mantel':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_taxa2taxon':
                        $fileToParse = '/cmd_line_output.txt';
                        break;
                case 'parallel_permanova':
                        $fileToParse = '/cmd_line_output.txt';
                        break;
                default:
                    $fileToParse = '/job'.$job_id.'.Rout';
            }
            
            // If job has run, check for R errors
            if($status == 'completed'){
                $parser = new RvlabParser();
                $parser->parse_output($job_folder.$fileToParse);        
                if($parser->hasFailed()){
                    $status = 'failed'; 
                }
            }                 
        }

        $job->status = $status;
        $job->save();
        
        // IF job was completed successfully use it for statistics
        if($status == 'completed'){
            $this->log_event("JobsLog to be created by JobController for job ".$job->id."with status ".$job->status." by user ".$job->user_email,"info");
            
            $job_log = new JobsLog();
            $job_log->id = $job->id;
            $job_log->user_email = $job->user_email;
            $job_log->function = $job->function;
            $job_log->status = $job->status;
            $job_log->submitted_at = $job->submitted_at;
            $job_log->started_at = $job->started_at;
            $job_log->completed_at = $job->completed_at;
            $job_log->jobsize = $job->jobsize;
            $job_log->inputs = $job->inputs;
            $job_log->save();                        
        }
    }    
    
    /**
     * Displays the results page of a job
     * 
     * @param int $job_id
     * @return View|JSON
     */
    public function job_page($job_id){
        
        $user_email = $this->user_status['email']; 
        $job_record = DB::table('jobs')->where('user_email',$user_email)->where('id',$job_id)->first();
        $data['job'] = $job_record;
        
        // In case job id wasn't found
        if(empty($job_record)){
            Session::flash('toastr',array('error','You have not submitted recently any job with such ID!'));
            if($this->is_mobile){
                $response = array('message','You have not submitted recently any job with such ID!');
                return Response::json($response,400);
            } else {
                return Redirect::back();
            }            
        }
        
        // Load information about input files 
        $inputs = array();
        $input_files = explode(';',$job_record->inputs);        
        foreach($input_files as $ifile){
            $info = explode(':',$ifile);
            $id = $info[0];
            $filename = $info[1];
            $record = WorkspaceFile::where('id',$id)->first();
            if(empty($record)){
                $exists = false;
            } else {
                $exists = true;
            }
            $inputs[] = array(
                'id'    =>  $id,
                'filename'  =>  $filename,
                'exists'    =>  $exists
            );
        }        
        
        // If job execution has not finished, try to update its status
        if(in_array($job_record->status,array('submitted','running','queued'))){
            $this->refresh_single_status($job_id);
        }
        
        $selected_function = $job_record->function;
        $data['function'] = $selected_function;
        
        // Decide which file should be parsed
        switch($selected_function){
            case 'simper':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
            case 'bict':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_anosim':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_taxa2dist':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_postgres_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
            case 'parallel_mantel':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_taxa2taxon':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_permanova':
                $fileToParse = '/cmd_line_output.txt';
                break;
            default:
                $fileToParse = '/job'.$job_id.'.Rout';
        }
        
        // If job has failed
        if($job_record->status == 'failed'){
            $job_folder = $this->jobs_path.'/'.$job_record->user_email.'/job'.$job_id;
            $log_file = $job_folder."/job".$job_id.".log";
            
            $parser = new RvlabParser();
            $parser->parse_output($job_folder.$fileToParse);
            if($parser->hasFailed()){
                $data['errorString'] = implode("<br>",$parser->getOutput());
                $data['errorString'] .= $parser->parse_log($log_file);
            } else {
                $data['errorString'] = "Error occured during submission.";
                $data['errorString'] .= $parser->parse_log($log_file);
            }
            if($this->is_mobile){
                $response = array('message','Error occured during submission.');
                return Response::json($response,500);
            } else {
                return $this->load_view('results/failed','Job Results',$data); 
            }              
        }
        
        // If job is pending
        if(in_array($job_record->status,array('submitted','queued','running'))){
            if($this->is_mobile){
                $response = array('data',$data);
                return Response::json($response,500);
            } else {
                $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
                return $this->load_view('results/submitted','Job Results',$data);  
            }               
        }
                
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $user_workspace = $this->workspace_path.'/'.$user_email;
        
        // Build the result page for this job
        $low_function = strtolower($selected_function);        
        return $this->{$low_function.'_results'}($job_id,$job_folder,$user_workspace,$inputs);
        
    }

    /**
     * Submits a new job
     * Handles the basic functionlity of submission that is not related to a specific R vLab function
     * 
     * @return RedirectResponse|JSON
     */
    public function submit(){        
        try {
            $form = Input::all();        
            $function_select = $form['function'];
            $user_email = $this->user_status['email'];                        

            // Validation
            if(empty($form['box'])) {
                if($this->is_mobile){
                    $response = array('message','You forgot to select an input file!');
                    return Response::json($response,400);
                } else {
                    Session::flash('toastr',array('error','You forgot to select an input file!'));
                    return Redirect::back();
                }               
            } else {
                $box= $form['box'];
            }
        
        } catch(Exception $ex){
            $this->log_event($ex->getMessage(),"error");
        }
                
        try {
            // Create a job record
            $job = new Job();
            $job->user_email = $user_email;
            $job->function = $function_select;
            $job->status = 'creating';
            $job->submitted_at = date("Y-m-d H:i:s");
            $job->save();

            // Get the job id and create the job folder
            $job_id = 'job'.$job->id;
            $user_jobs_path = $this->jobs_path.'/'.$user_email;
            $job_folder = $user_jobs_path.'/'.$job_id;
            $user_workspace = $this->workspace_path.'/'.$user_email;       
            

            // Create the required folders if they are not exist
            if(!file_exists($user_workspace)){
                if(!mkdir($user_workspace)){
                    $this->log_event('User workspace directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','User workspace directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }                      
                }                    
            }
            if(!file_exists($user_jobs_path)){              
                if(!mkdir($user_jobs_path)){
                    $this->log_event('User jobs directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','User jobs directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }  
                }
            }
            
            if(!file_exists($job_folder)){
                if(!mkdir($job_folder)){
                    $this->log_event('Job directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','Job directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }
                }
            }
            $remote_job_folder = $this->remote_jobs_path.'/'.$user_email.'/'.$job_id;
            $remote_user_workspace = $this->remote_workspace_path.'/'.$user_email;
            
            // Run the function
            if(is_array($form['box']))
                $inputs = implode(';',$form['box']);                
            else
                $inputs = $form['box'];
            $low_function = strtolower($function_select);
            $submitted = $this->{$low_function}($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,$inputs);
            if(!$submitted){
                
                $this->log_event('Function '.$low_function.' failed!',"error");
                //Session::flash('toastr',array('error','New job submission failed!'));
                
                // Delete the job record
                Job::where('id',$job->id)->delete();
               
                 // Delete folder if created
                if(file_exists($job_folder)){
                    if(!rmdir_recursive($job_folder)){
                        $this->log_event('Folder '.$job_folder.' could not be deleted after failed job submission!',"error");
                    }
                }
                
                if($this->is_mobile){
                    $response = array('message','New job submission failed!');
                    return Response::json($response,500);
                } else {
                    return Redirect::back();
                }
            }
            
            $input_ids = array();
            $inputs_list = explode(';',$inputs);
            foreach($inputs_list as $input){
                $file_record = WorkspaceFile::whereRaw('BINARY filename LIKE ?',array($input))->first();
                $input_ids[] = $file_record->id.":".$input;
            }
            $input_ids_string = implode(';',$input_ids);
            
            
            $job->status = 'submitted';
            $job->jobsize = directory_size($job_folder);
            $job->inputs = $input_ids_string;
            $job->save();                        
            
        } catch (Exception $ex) { 
            // Delete record if created
            if(!empty($job_id)){
                $job->delete();
            }            
            // Delete folder if created
            if(file_exists($job_folder)){
                if(!rmdir_recursive($job_folder)){
                    $this->log_event('Folder '.$job_folder.' could not be deleted!',"error");
                }
            }
            
            $this->log_event($ex->getMessage(),"error");
            Session::flash('toastr',array('error','New job submission failed!'));
            if($this->is_mobile){
                $response = array('message','New job submission failed!');
                return Response::json($response,500);
            } else {
                return Redirect::back();
            }
        }
            
        Session::put('last_function_used',$function_select);
        Session::flash('toastr',array('success','The job submitted successfully!'));
        //$this->log_event("New job submission","info");
        if($this->is_mobile){
            return Response::json(array(),200);
        } else {
             return Redirect::to('/');
        }       
        
    }

    /**
     * Handles the part of job submission functionlity that relates to taxa2dist function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function taxa2dist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        $box= $form['box'];          
        
        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        }
        
        if(empty($form['check_taxa2dist'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $check = $form['check_taxa2dist'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "agg <- read.table(\"".$remote_job_folder."/".$box."\", header = TRUE, sep=\",\");\n");
        fwrite($fh, "taxdis <- taxa2dist(agg, varstep=$varstep, check=$check);\n");
        fwrite($fh, "save(taxdis, ascii=TRUE, file = \"$remote_job_folder/taxadis.csv\");\n");
        fwrite($fh, "summary(taxdis);\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");    
        return true;
    }

    /**
     * Loads job results information related to taxa2dist.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function taxa2dist_results($job_id,$job_folder,$user_workspace,$input_files){                
        
        $data = array();
        $data['function'] = "taxa2dist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }            
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "taxadis";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;
        
        if($this->is_mobile){
            return Response::json(array('data',$data),200);            
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }            
        
    }
    
    /**
     * Handles the part of job submission functionlity that relates to taxondive function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function taxondive($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        if(empty($form['deltalamda'])){
            Session::flash('toastr',array('error','You forgot to select delta or lamda parameter!'));
            return false;
        } else {
            $deltalamda = $form['deltalamda'];
        }
        
        $box= $form['box']; 
        $box2 = $form['box2'];  
        $inputs .= ";".$box2;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        if(!empty($form['box3'])){
            $box3 = $form['box3'];
            $workspace_filepath = $user_workspace.'/'.$box3;
            $job_filepath = $job_folder.'/'.$box3;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
            $inputs .= ";".$box3;
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file: $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "taxdis <- get(load(\"$remote_job_folder/$box2\"));\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");
        
        if(!empty($form['transpose'])){
            fwrite($fh, "mat <- t(mat);\n");
        }

        $transformation_method = $form['transf_method_select'];
        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }
        
        $match_force = $form['match_force']; 
        fwrite($fh, "taxondive <- taxondive(mat,taxdis,match.force=$match_force);\n");
        fwrite($fh, "save(taxondive, ascii=TRUE, file = \"$remote_job_folder/taxondive.csv\");\n");
                
        if(empty($box3)){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{
            $column_select = $form['column_select'];
            fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box3\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$column_select);\n");
        }
        fwrite($fh, "png('legend.png',height = 700, width = 350)\n");
        fwrite($fh, "plot(mat, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        if(empty($box3)){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('rplot.png',height = 600, width = 600)\n");
        if($deltalamda=="Delta"){           
            fwrite($fh, "if(min(taxondive\$Dplus) < min(taxondive\$EDplus-taxondive\$sd.Dplus*2)){\n");
            fwrite($fh, "plot(taxondive,pch=19,col=labels,cex = 1.7, ylim = c(min(taxondive\$Dplus),max(taxondive\$sd.Dplus*2+taxondive\$EDplus)), xlim = c(min(taxondive\$Species),max(taxondive\$Species)));\n");
            fwrite($fh, "}else if(max(taxondive\$Dplus) > max(taxondive\$sd.Dplus*2+taxondive\$EDplus)){\n");
            fwrite($fh, "plot(taxondive,pch=19,col=labels,cex = 1.7, ylim = c(min(taxondive\$EDplus-taxondive\$sd.Dplus*2),max(taxondive\$Dplus)), xlim = c(min(taxondive\$Species),max(taxondive\$Species)))\n");
            fwrite($fh, "}else{\n");
            fwrite($fh, "plot(taxondive,pch=19,col=labels,cex = 1.7,xlim = c(min(taxondive\$Species),max(taxondive\$Species)), ylim = c(min(taxondive\$EDplus-taxondive\$sd.Dplus*2),max(taxondive\$sd.Dplus*2+taxondive\$EDplus)))\n");
            fwrite($fh, "}\n");#
            fwrite($fh, "with(taxondive, text(Species-.3, Dplus-1, as.character(rownames(mat)),pos = 4, cex = 0.9))\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(taxondive);\n");

        }else{
            fwrite($fh, "lambda1 <- taxondive\$Lambda\n");
            fwrite($fh, "Species1 <- taxondive\$Species\n");
            fwrite($fh, "lambda_dat <- as.matrix(lambda1)\n");

            fwrite($fh, "colnames(lambda_dat) <- c(\"L\")\n");
            fwrite($fh, "#lambda_dat <- lambda_dat[,-1]\n");

            fwrite($fh, "Species_dat <- as.matrix(Species1)\n");
            fwrite($fh, "colnames(Species_dat) <- c(\"Species\")\n");
            fwrite($fh, "data2 <- merge(lambda_dat,Species_dat,by=\"row.names\")\n");

            fwrite($fh, "#taxondive\$Dplus <- taxondive\$Lambda\n");

            fwrite($fh, "fit <- lm(L~Species, data=data2)\n");

            fwrite($fh, "#confint(fit, 'Species', level=0.95)\n");

            fwrite($fh, "newx <- seq(min(data2\$Species), max(data2\$Species), length.out=n)\n");
            fwrite($fh, "preds <- predict(fit,  interval = 'confidence')\n");
            fwrite($fh, "order.fit <- order(preds[,1])\n");
            fwrite($fh, "preds<- preds[order.fit,]\n");

            fwrite($fh, "#preds2\n");
            fwrite($fh, "# plot\n");
            fwrite($fh, "plot(L ~ Species, data = data2, type = 'p',pch=19,cex=1.7,col=labels)\n");
            fwrite($fh, "# add fill\n");
            fwrite($fh, "#polygon(c(rev(newx), newx), c(rev(preds[ ,3]), preds[ ,2]), col = 'grey80', border = NA)\n");
            fwrite($fh, "# model\n");
            fwrite($fh, "abline(h=mean(data2\$L),col='red')\n");
            fwrite($fh, "# intervals\n");
            fwrite($fh, "lines(newx, rev(preds[ ,3]), lty = 1, col = 'red')\n");
            fwrite($fh, "lines(newx, preds[ ,2], lty = 1, col = 'red')\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(taxondive);\n");                                        
        }
        fwrite($fh, "summary(taxondive);\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);        

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to taxondive.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function taxondive_results($job_id,$job_folder,$user_workspace,$input_files){
            
        $data = array();
        $data['function'] = "taxondive";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }                
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "taxondive";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
            	
    }
    
    /**
     * Handles the part of job submission functionlity that relates to vegdist function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function vegdist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        $box = $form['box'];
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transf_method_select parameter!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }
        
        if(empty($form['binary_select'])){
            Session::flash('toastr',array('error','You forgot to set the binary_select parameter!'));
            return false;
        } else {
            $bin = $form['binary_select'];
        }
        
        if(empty($form['diag_select'])){
            Session::flash('toastr',array('error','You forgot to set the diag_select parameter!'));
            return false;
        } else {
            $diag = $form['diag_select'];
        }
        
        if(empty($form['upper_select'])){
            Session::flash('toastr',array('error','You forgot to set the upper_select parameter!'));
            return false;
        } else {
            $upper = $form['upper_select'];
        }
        
        if(empty($form['na_select'])){
            Session::flash('toastr',array('error','You forgot to set the na_select parameter!'));
            return false;
        } else {
            $na = $form['na_select'];
        }        

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "vegdist <- vegdist(mat, method = \"$method_select\",binary=$bin, diag=$diag, upper=$upper,na.rm = $na)\n");
        fwrite($fh, "save(vegdist, ascii=TRUE, file = \"$remote_job_folder/vegdist.csv\");\n");                                   
        fwrite($fh, "summary(vegdist);\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &"); 
        return true;
    }
    
    /**
     * Loads job results information related to vegdist.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function vegdist_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "vegdist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "vegdist";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;
        
        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to hclust function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function hclust($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        $box = $form['box'];
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Method!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }
        
        if(!empty($form['box2'])){
            $box2 = $form['box2'];
            if(!empty($form['column_select'])){
                $column_select = $form['column_select'];
            } else {
                Session::flash('toastr',array('error','You forgot to set the column in the factor file!'));
                return false;
            }
            $inputs .= ";".$box2;
        } else {
            $box2 = "";
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        if(!empty($box2)){
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }  
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");        

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "library(dendextend);\n");
        fwrite($fh, "dist <- get(load(\"$remote_job_folder/$box\"));\n");
        fwrite($fh, "clust.average <- hclust(dist, method = \"$method_select\")\n");
        fwrite($fh, "dend <- as.dendrogram(clust.average);\n");

        if(!empty($box2)){            
            fwrite($fh, "Groups <- read.table(\"$remote_job_folder/$box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "groupCodes <- Groups\$$column_select;\n");            
            fwrite($fh, "# Assigning the labels of dendrogram object with new colors:;\n");
            fwrite($fh, "labels_cols <- rainbow(length(groupCodes))[rank(groupCodes)];\n");
            fwrite($fh, "labels_cols <- labels_cols[order.dendrogram(dend)];\n");
            fwrite($fh, "groupCodes <- groupCodes[order.dendrogram(dend)];\n");
            fwrite($fh, "labels_colors(dend) <- labels_cols;\n");            
            fwrite($fh, "png('legend.png',height = 700,width=350)\n");
            fwrite($fh, "plot(dist, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
            fwrite($fh, "legend(\"topright\", legend=unique(groupCodes), col=unique(labels_cols), pch = 16);\n");
            fwrite($fh, "dev.off()\n");
        }

        fwrite($fh, "png('rplot.png',height = 600, width = 600)\n");                   
        fwrite($fh, "plot(dend)\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "summary(clust.average);\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        
        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");  
        return true;
    }
    
    /**
     * Loads job results information related to hclust.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function hclust_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "hclust";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';  
        $data['job_folder'] = $job_folder;
        
        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to bict function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function bict($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
                
        if(empty($form['species_family_select'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }                
        
        $box= $form['box']; 
        $sp_fam = $form['species_family_select'];        

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        if(!empty($form['box2'])){
            $box2= $form['box2']; 
            $inputs .= ";".$box2;
            
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }                       

        // Move a required file
        $script_source = app_path().'/rvlab/files/indices';
        copy($script_source,"$job_folder/indices");
        
        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");   
        fwrite($fh2, "date\n");

        if($sp_fam == 'species'){
            if(empty($box2)){
                fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box >$remote_job_folder/tmp.csv\n");
                fwrite($fh2, "$remote_job_folder/indices -i$remote_job_folder/tmp.csv -o$remote_job_folder/indices.txt -B/dev/null -X/dev/null -A/dev/null > $remote_job_folder/cmd_line_output.txt \n");
            } else {                        
                fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box2 > $remote_job_folder/tmp2.csv\n");
                fwrite($fh2, "$remote_job_folder/indices -i$remote_job_folder/tmp.csv -d$remote_job_folder/tmp2.csv -o$remote_job_folder/indices.txt -B/dev/null -X/dev/null -A/dev/null > $remote_job_folder/cmd_line_output.txt\n");
            }
        } else {
            fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box >$remote_job_folder/tmp.csv\n");
            fwrite($fh2, "$remote_job_folder/indices -i$remote_job_folder/tmp.csv -f -o$remote_job_folder/indices.txt -F/dev/null > $remote_job_folder/cmd_line_output.txt\n");
        }
        fwrite($fh2, "date\n");        
        fwrite($fh2, "exit 0");

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("chmod +x $job_folder/indices");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to bict.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function bict_results($job_id,$job_folder,$user_workspace,$input_files){
        
        $results = "<br>";
        
        $data = array();
        $data['function'] = "bict";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        } else {
            if(file_exists($job_folder."/indices.txt")){
                $handle = fopen($job_folder."/indices.txt", "r");
                if ($handle) {
                    while (($textline = fgets($handle)) !== false) {  
                        $results .= $textline."<br>";                                                  
                    }
                    fclose($handle);
                }
            }            
        }
        
        // If the results file exists, then add the contents of this file
        // to the job page 
        $data['lines'] = $parser->getOutput();
        $data['lines'][] = $results;        
        
        $data['images'] = array();
        $data['dir_prefix'] = "indices";
        $data['blue_disk_extension'] = '.txt';  
        $data['job_folder'] = $job_folder;
        
        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
        
    }
    
    /**
     * Handles the part of job submission functionlity that relates to metamds function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function metamds($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){               
        
        $box= $form['box'];           
        
        if(!empty($form['column_select'])){
            $column_select = $form['column_select'];
        } else {
            if(!empty($form['box2'])){
                Session::flash('toastr',array('error','You forgot to select column!'));
                return false;
            }
        }
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Method parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }
        
        if(empty($form['k_select'])){
            Session::flash('toastr',array('error','You forgot to set the K parameter!'));
            return false;
        } else {
            $K = $form['k_select'];
        }
        
        if(empty($form['trymax'])){
            Session::flash('toastr',array('error','You forgot to set the trymax parameter!'));
            return false;
        } else {
            $trymax = $form['trymax'];
        }
        
        if(empty($form['autotransform_select'])){
            Session::flash('toastr',array('error','You forgot to set the autotransform parameter!'));
            return false;
        } else {
            $autotransform_select = $form['autotransform_select'];
        }
        
        if(empty($form['noshare'])){
            Session::flash('toastr',array('error','You forgot to set the noshare parameter!'));
            return false;
        } else {
            $noshare = $form['noshare'];
        }
        
        if(empty($form['wascores_select'])){
            Session::flash('toastr',array('error','You forgot to set the wascores_select parameter!'));
            return false;
        } else {
            $wascores_select = $form['wascores_select'];
        }
        
        if(empty($form['expand'])){
            Session::flash('toastr',array('error','You forgot to set the expand parameter!'));
            return false;
        } else {
            $expand = $form['expand'];
        }
        
        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        if(!empty($form['box2'])){
            $box2 = $form['box2'];
            $inputs .= ";".$box2;
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");
        
        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }            

        if(empty($form['box2'])){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{            
            fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$column_select);\n");
        }

        fwrite($fh, "otu.nmds <- metaMDS(mat,distance=\"$method_select\");\n");//,k = $K, trymax = $trymax, autotransform =$autotransform_select,noshare = $noshare, wascores = $wascores_select, expand = $expand, trace = $trace);\n");
        fwrite($fh, "par(xpd=TRUE);\n");
        fwrite($fh, "png('legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(otu.nmds, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        if(empty($form['box2'])){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(otu.nmds, type = \"n\")\n");
        fwrite($fh, "points(otu.nmds, col = labels, pch = 16,cex = 1.7);\n");//           
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.nmds;\n");                    
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");     
        return true;
    }
    
    /**
     * Loads job results information related to metamds.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function metamds_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "metamds";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to second_metamds function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function second_metamds($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        $box = $form['box'];
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Method parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }
        
        if(empty($form['k_select'])){
            Session::flash('toastr',array('error','You forgot to set the K parameter!'));
            return false;
        } else {
            $K = $form['k_select'];
        }
        
        if(empty($form['trymax'])){
            Session::flash('toastr',array('error','You forgot to set the trymax parameter!'));
            return false;
        } else {
            $trymax = $form['trymax'];
        }
        
        if(empty($form['autotransform_select'])){
            Session::flash('toastr',array('error','You forgot to set the autotransform parameter!'));
            return false;
        } else {
            $autotransform_select = $form['autotransform_select'];
        }
        
        if(empty($form['noshare'])){
            Session::flash('toastr',array('error','You forgot to set the noshare parameter!'));
            return false;
        } else {
            $noshare = $form['noshare'];
        }
        
        if(empty($form['wascores_select'])){
            Session::flash('toastr',array('error','You forgot to set the wascores_select parameter!'));
            return false;
        } else {
            $wascores_select = $form['wascores_select'];
        }
        
        if(empty($form['expand'])){
            Session::flash('toastr',array('error','You forgot to set the expand parameter!'));
            return false;
        } else {
            $expand = $form['expand'];
        }
        
        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        }        
            
        // Move input file from workspace to job's folder        
        foreach($box as $box_file){
            $workspace_filepath = $user_workspace.'/'.$box_file;
            $job_filepath = $job_folder.'/'.$box_file;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
            exit("Unable to open file $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "library(ecodist);\n");
        $filecount = 1;
        fwrite($fh, "# replace missing data with 0;\n");
        fwrite($fh, "# fourth root or any other transformation here, excluding first column with taxon names;\n");
        fwrite($fh, "#transpose the matrices, bcdist needs rows as samples;\n");
        fwrite($fh, "# calculate bray curtis for all;\n");
        foreach ($box as $val) {
            $inputs .= ";".$val;
            
            fwrite($fh, "mat".$filecount." <- read.table(\"$remote_job_folder/$val\", header = TRUE, sep=\",\",row.names=1);\n");                
            if($transpose == "transpose"){
                fwrite($fh, "mat".$filecount." <- t(mat".$filecount.");\n");
            }               

            if($transformation_method != "none"){
                fwrite($fh, "mat".$filecount." <- decostand(mat".$filecount.", method = \"$transformation_method\");\n");
            }

            fwrite($fh, "mat".$filecount."[is.na(mat".$filecount.")]<-0;\n");
            fwrite($fh, "mat".$filecount."_2 <- sqrt(sqrt(mat".$filecount."));\n");//[,-1]
            fwrite($fh, "mat".$filecount."_tr <- t(mat".$filecount."_2);\n");
            fwrite($fh, "bc".$filecount." <-bcdist(mat".$filecount."_tr);\n");               
            $filecount++;

        }
        fwrite($fh, "#create an empty matrix to fill in the correlation coefficients;\n");
        $filecount = $filecount-1;
        fwrite($fh, "bcs <- matrix(NA, ncol=".$filecount.", nrow=".$filecount.");\n");            
        fwrite($fh,"combs <- combn(1:$filecount, 2);\n");
        fwrite($fh,"for (i in 1:ncol(combs) ) {\n");
        fwrite($fh, "bc1_t <- paste(\"bc\",combs[1,i],sep=\"\");\n");
        fwrite($fh, "bc2_t <- paste(\"bc\",combs[2,i],sep=\"\");\n");                                
        fwrite($fh, "bcs[combs[1,i],combs[2,i]] <- cor(get(bc1_t), get(bc2_t), method=\"spearman\");\n");
        fwrite($fh,"}\n");
        fwrite($fh,"bcs <- t(bcs)\n");                        
        fwrite($fh, "x <- c(\"$box[0]\");\n");
        for ($j=1; $j<sizeof($box); $j++) {
            fwrite($fh, "x <- append(x, \"$box[$j]\");\n");
        }
        fwrite($fh, "colnames(bcs) <-x;\n");
        fwrite($fh, "rownames(bcs) <-x;\n");

        fwrite($fh, "#transform the matrix into a dissimlarity matrix of format \"dis\";\n");
        fwrite($fh, "dist1 <- as.dist(bcs, diag = FALSE, upper = FALSE);\n");

        fwrite($fh, "#dist2 <- as.dist(NA);\n");
        fwrite($fh, "dist2<- 1-dist1;\n");

        fwrite($fh, "#run the mds;\n");
        fwrite($fh, "mydata.mds<- metaMDS(dist2,  k = $K, trymax = $trymax,distance=\"$method_select\");\n");
        fwrite($fh, "save(dist2, ascii=TRUE, file = \"$remote_job_folder/dist_2nd_stage.csv\");\n");
        
        fwrite($fh, "png('legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(mydata.mds, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        fwrite($fh, "n<- length(x);\n");
        fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
        fwrite($fh, "labels <- rain;\n");
        fwrite($fh, "legend(\"topright\", legend=x, col=labels, pch = 16);\n");
        fwrite($fh, "dev.off()\n");


        fwrite($fh, "#plot the empty plot;\n");
        fwrite($fh, "png('rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(mydata.mds, type=\"n\");\n");
        fwrite($fh, "par(mar=c(5.1, 8.1, 4.1, 8.1), xpd=TRUE);\n");
        fwrite($fh, "#add the points for the stations, blue with red circle;\n");
        fwrite($fh, "points(mydata.mds, display = c(\"sites\", \"species\"), cex = 1.8, pch=19, col=labels);\n");#0.8
        fwrite($fh, "# add the labels for the stations;\n");
        fwrite($fh, "text(mydata.mds, display = c(\"sites\", \"species\"), cex = 1.0 , pos=3 );\n");#0.7, 3
        fwrite($fh, "dev.off()\n");

            
        fwrite($fh, "#alternative plotting:;\n");
        fwrite($fh, "#ordipointlabel(mydata.mds, display =\"spec\");\n");
        fwrite($fh, "#points(mydata.mds, display = \"spec\", cex = 1.0, pch=20, col=\"red\", type=\"t\"');\n");

        fwrite($fh, "#alternative plotting - allows to drag the labels to a better position and then export the graphic as EPS;\n");
        fwrite($fh, "#orditkplot(mydata.mds) ;\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "mydata.mds;\n");            			
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");        
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);     

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to second_metamds.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function second_metamds_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "second_metamds";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "dist_2nd_stage";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to pca function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function pca($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){                        
        
        $box= $form['box'];          
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }
        
        if(!empty($form['transpose'])){
            $transpose = $form['transpose'];
        } else {
            $transpose = "";
        }
        
        if(!empty($form['box2'])){
            $box2 = $form['box2'];
            if(!empty($form['column_select'])){
                $column_select = $form['column_select'];
            } else {
                Session::flash('toastr',array('error','You forgot to set the column in the factor file!'));
                return false;
            }
            $inputs .= ";".$box2;
        } else {
            $box2 = "";
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }                
        
        if(!empty($box2)){
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }  
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }			

        if(empty($box2)){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{            
            fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$column_select);\n");
        }            

        fwrite($fh, "otu.pca <- rda(mat);\n");
        fwrite($fh, "par(xpd=TRUE);\n");
        fwrite($fh, "png('$remote_job_folder/legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");

        if(empty($box2)){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }

        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('$remote_job_folder/rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\")\n");
        fwrite($fh, "points(otu.pca, col = labels, pch = 16,cex = 1.7);\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.pca;\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
        
    }
    
    /**
     * Loads job results information related to pca.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function pca_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "pca";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to cca function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function cca($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        $box= $form['box']; 
        $box2 = $form['box2']; 
        $inputs .= ";".$box2;
        
        if(!isset($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to select the Factor1 column!'));
            return false;
        } 
        
        if(!isset($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to select the Factor2 column!'));
            return false;
        }
        
        if(!isset($form['Factor_select3'])){
            Session::flash('toastr',array('error','You forgot to select the Factor3 column!'));
            return false;
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        }
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }                        
        
        $l_1 = $form['Factor_select1'];
        $l_2 = $form['Factor_select2'];
        $l_3 = $form['Factor_select3'];
        $transformation_method = $form['transf_method_select'];
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file: $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");       
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", row.names=1, header = TRUE, sep=\",\");\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        
        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");                   
        }
        
        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        if(empty($l_3)){
            fwrite($fh, "vare.cca <- cca(mat ~ $l_1+$l_2, data=ENV);\n");
        } else {           
            fwrite($fh, "vare.cca <- cca(mat ~ $l_1+$l_2+$l_3, data=ENV);\n");
        }

        fwrite($fh, "png('rplot.png',height=600,width=600)\n");
        fwrite($fh, "plot(vare.cca);\n");
        fwrite($fh, "summary(vare.cca);\n");                                                
        fwrite($fh, "dev.off()\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");        
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2); 

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;     
            
    }
    
    /**
     * Loads job results information related to cca.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function cca_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "cca";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to regression function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function regression($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        $box= $form['box'];    
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }
        
        if(empty($form['single_or_multi'])){
            Session::flash('toastr',array('error','You forgot to set the regression type (single or multi)!'));
            return false;
        } else {
            $single_or_multi = $form['single_or_multi'];
        }
        
        if(empty($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to set Factor 1!'));
            return false;
        } else {
            $Factor1 = $form['Factor_select1'];
        }
        
        if(empty($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to set Factor 2!'));
            return false;
        } else {
            $Factor2 = $form['Factor_select2'];
        }
        
        if(empty($form['Factor_select3'])){
            $Factor3 = "";
        } else {
            $Factor3 = $form['Factor_select3'];
        }

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");
        
        fwrite($fh, "library(stats);\n");
        fwrite($fh, "library(vegan);\n");                
        fwrite($fh, "fact <- read.table(\"$remote_job_folder/$box\", row.names=1, header = TRUE, sep=\",\");\n");
        
        
        if($transformation_method != "none"){
            fwrite($fh, "fact <- decostand(fact, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "attach(fact);\n");
        if($single_or_multi =="single"){
            fwrite($fh, "fit<-lm($Factor1~$Factor2);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot($Factor1~$Factor2)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
            fwrite($fh, "abline(fit, col=\"red\")\n");
            fwrite($fh, "dev.off()\n");

        }else{
            fwrite($fh, "fit<-lm($Factor1~$Factor2+$Factor3);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot($Factor1~$Factor2+$Factor3)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
            fwrite($fh, "abline(fit, col=\"red\")\n");
            fwrite($fh, "dev.off()\n");
        }
        fwrite($fh, "png('rplot2.png')\n");
        fwrite($fh, "layout(matrix(c(1,2,3,4),2,2))\n");
        fwrite($fh, "plot(fit)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "summary(fit);\n");                      
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");    
        return true;
    }
    
    /**
     * Loads job results information related to regression.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function regression_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "regression";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','rplot2.png');
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to anosim function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function anosim($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
                
        $box= $form['box']; 
        $box2 = $form['box2'];          
        $inputs .= ";".$box2;        
        $transformation_method = $form['transf_method_select'];  
        
        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select factor column!'));
            return false;
        } else {
            $column_select = $form['column_select'];
        }
        
        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set permutations!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "otu.ENVFACT.anosim <- anosim(mat,ENV\$$column_select,permutations = $permutations,distance = \"$method_select\");\n");
        fwrite($fh, "png('rplot.png')\n");
        fwrite($fh, "plot(otu.ENVFACT.anosim)\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.anosim\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);        

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");        
        return true;
    }
    
    /**
     * Loads job results information related to anosim.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function anosim_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "anosim";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to anova function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function anova($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){        

        $box= $form['box']; 
        
        if(empty($form['one_or_two_way'])){
            Session::flash('toastr',array('error','You forgot to select between one-way and two-way anova!'));
            return false;
        } else {
            $one_or_two_way = $form['one_or_two_way'];
        }
        
        if(empty($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to select Factor 1!'));
            return false;
        } else {
            $Factor1 = $form['Factor_select1'];
        }
        
        if(empty($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to select Factor 2!'));
            return false;
        } else {
            $Factor2 = $form['Factor_select2'];
        }

        if(!empty($form['Factor_select3'])){
            $Factor3 = $form['Factor_select3'];
        } else {
            $Factor3 = "";
        }

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");
        
        fwrite($fh, "library(stats);\n"); 
        fwrite($fh, "geo <- read.table(\"$remote_job_folder/$box\", row.names=1, header = TRUE, sep=\",\");\n");

        if($one_or_two_way =="one"){
            fwrite($fh, "aov.ex1<-aov($Factor1~$Factor2,geo);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "boxplot($Factor1~$Factor2,geo,xlab=\"$Factor2\", ylab=\"$Factor1\")\n");
            fwrite($fh, "dev.off()\n");
        } else {
            fwrite($fh, "aov.ex1<-aov($Factor1~$Factor2*$Factor3,geo);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "boxplot($Factor1~$Factor2*$Factor3,geo)\n");
            fwrite($fh, "dev.off()\n");
        }
        fwrite($fh, "summary(aov.ex1);\n");                                                 
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);  

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");    
        return true;
    }
    
    /**
     * Loads job results information related to anova.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function anova_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "anova";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to permanova function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function permanova($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        $box = $form['box'];
        
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select a factor file!'));
            return false;
        } else {
            $box2=$form['box2'];
            $inputs .= ";".$box2;
        }

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to select the transformation method!'));
            return false;
        } else {
            $transformation_method=$form['transf_method_select'];
        }
        
        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select the Factor1 column!'));
            return false;
        } else {
            $column_select=$form['column_select'];
        }
        
        if(empty($form['column_select2'])){
            Session::flash('toastr',array('error','You forgot to select the Factor2 column!'));
            return false;
        } else {
            $column_select2=$form['column_select2'];
        }
        
        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations!'));
            return false;
        } else {
            $permutations=$form['permutations'];
        }
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to select method!'));
            return false;
        } else {
            $method_select=$form['method_select'];
        }
        
        if(empty($form['single_or_multi'])){
            Session::flash('toastr',array('error','You forgot to select between single or multiple parameter!'));
            return false;
        } else {
            $single_or_multi=$form['single_or_multi'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file: $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");
        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }
        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }                                
        if($single_or_multi =="single"){
            fwrite($fh, "otu.ENVFACT.adonis <- adonis(mat ~ ENV\$$column_select,data=ENV,permutations = $permutations,distance = \"$method_select\");\n");
        }else{
            fwrite($fh, "otu.ENVFACT.adonis <- adonis(mat ~ ENV\$$column_select+ENV\$$column_select2,data=ENV,permutations = $permutations,distance = \"$method_select\");\n");
        }

        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.adonis\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);  

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to permanova.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function permanova_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "permanova";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to metamds_visual function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function metamds_visual($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        $box= $form['box'];                   
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['method_select_viz'])){
            Session::flash('toastr',array('error','You forgot to set the Method parameter!'));
            return false;
        } else {
            $method_select = $form['method_select_viz'];
        }
        
        if(empty($form['k_select_viz'])){
            Session::flash('toastr',array('error','You forgot to set the K parameter!'));
            return false;
        } else {
            $K = $form['k_select_viz'];
        }
        
        if(empty($form['trymax_viz'])){
            Session::flash('toastr',array('error','You forgot to set the trymax parameter!'));
            return false;
        } else {
            $trymax = $form['trymax_viz'];
        }
        
        if(empty($form['top_species'])){
            Session::flash('toastr',array('error','You forgot to set the number of top ranked species!'));
            return false;
        } else {
            $top_species = $form['top_species'];
        }        
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Move a required file
        $script_source = app_path().'/rvlab/files/summarize.html';
        copy($script_source,"$job_folder/summarize.html");
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");
        
        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }
        
        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }           
                
        fwrite($fh, "MDS<-metaMDS(x, distance = \"$method_select\", k = $K, trymax = $trymax);\n");
        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "new_x2 <- t(new_x);\n");
        fwrite($fh, "write.table(new_x2, file = \"$remote_job_folder/filtered_abundance.csv\",sep=\",\",quote = FALSE,row.names = TRUE,col.names=NA);\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
        fwrite($fh, "sink(\"data.js\");\n");
        fwrite($fh, "cat(\"var freqData=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(new_x)[1])){  \n");
        fwrite($fh, "  cat(paste(\"{Samples:\'\",rownames(new_x)[i],\"\',\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"freq:{\",paste(paste(names,\":\",new_x[i,],sep=\"\"),collapse=\",\"),\"},\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"MDS:{\",paste(paste(colnames(MDS\$points),MDS\$points[rownames(new_x)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(new_x)[1]){cat(\",\")};\n");
        fwrite($fh, "  };\n");
        fwrite($fh, "          cat(\"];\");\n");
        fwrite($fh, "  sink();\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");     
        return true;
    }
    
    /**
     * Loads job results information related to metamds_visual.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function metamds_visual_results($job_id,$job_folder,$user_workspace,$input_files){
        
        $data = array();
        $data2 = array();
        
        $data['job_id'] = $job_id;
        $data['data_js'] = file($job_folder.'/data.js');
        $data2['content'] = View::make('results/metamds_visual',$data);        
        
        $data2['images'] = array();
        $data2['dir_prefix'] = "filtered_abundance";
        $data2['blue_disk_extension'] = '.csv';  
        $data2['function'] = "metamds_visual";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to mantel function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function mantel($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){        

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
                
        $box= $form['box']; 
        $box2 = $form['box2'];  
        $inputs .= ";".$box2;
        $method_select = $form['method_select'];
        $permutations = $form['permutations'];
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file: $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "dist1 <- get(load(\"$remote_job_folder/$box\"));\n");
        fwrite($fh, "dist2 <- get(load(\"$remote_job_folder/$box2\"));\n");
        fwrite($fh, "print(\"summary\")\n");


        fwrite($fh, "mantel.out <- mantel(dist1,dist2, method = \"$method_select\",permutations = $permutations)\n");
        fwrite($fh, "mantel.out\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);        

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to mantel.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function mantel_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "mantel";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);     
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to radfit function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function radfit($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){        

        $box = $form['box'];
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }        
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }
        
        if(!isset($form['column_radfit'])){
            Session::flash('toastr',array('error','You forgot to select a column!'));
            return false;
        } else {
            $column_radfit = $form['column_radfit'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");
        
        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }
        
        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }
                
        fwrite($fh, "library(vegan);\n");
        if($column_radfit == 0){
            fwrite($fh, "mod <- radfit(x)\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot(mod)\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(mod);\n");
        } else {
            fwrite($fh, "mod <- radfit(x[$column_radfit,])\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot(mod)\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(mod);\n");
        }
                
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");        
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &"); 
        return true;
    }
    
    /**
     * Loads job results information related to radfit.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function radfit_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "radfit";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';  
        $data['job_folder'] = $job_folder;
        
        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to parallel_taxa2dist function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function parallel_taxa2dist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        // Retrieve function configuration
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }        
        
        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        }
        
        if(empty($form['check_parallel_taxa2dist'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $check = $form['check_parallel_taxa2dist'];
        }
        
        $box = $form['box'];        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        $this->log_event($workspace_filepath. ' - '.$job_filepath,'info');
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
    
        // Build the R script
        $script_source = app_path().'/rvlab/files/Taxa2DistMPI.r';
        copy($script_source,"$job_folder/".$job_id.".R");

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=$no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $job_id.R $remote_job_folder/$box $remote_job_folder/ $remote_job_folder/ TRUE $varstep $check  output  > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2); 
        
        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to parallel_taxa2dist.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_taxa2dist_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_taxa2dist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "RvLAB_taxa2Distoutput";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to parallel_postgres_taxa2dist function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function parallel_postgres_taxa2dist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        // Retrieve function configuration
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }        
        
        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        }
        
        if(empty($form['check_parallel_taxa2dist'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $check = $form['check_parallel_taxa2dist'];
        }
        
        $box = $form['box'];        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        $this->log_event($workspace_filepath. ' - '.$job_filepath,'info');
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
    
        // Build the R script
        $script_source = app_path().'/rvlab/files/taxa2distPostgresMPI.r';
        copy($script_source,"$job_folder/".$job_id.".R");

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=$no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $job_id.R $remote_job_folder/$box 1000000 $remote_job_folder/ $remote_job_folder/ $job_id $varstep $check  > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2); 
        
        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to parallel_postgres_taxa2dist.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_postgres_taxa2dist_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_taxa2dist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "RvLAB_taxa2Distoutput";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to parallel_anosim function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function parallel_anosim($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        $box= $form['box']; 
        $box2 = $form['box2'];
        $inputs .= ";".$box2;
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations parameter!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }
        
        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select the column of factor file!'));
            return false;
        } else {
            $column_select = $form['column_select'];
        }
        
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }        

        if(empty($form['transpose'])){
            $transpose = "FALSE";
        } else {
            $transpose = 'TRUE';
        }
       
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        $script_source = app_path().'/rvlab/files/anosimMPI_24_09_2015.r';
        copy($script_source,"$job_folder/".$job_id.".R");       

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box $transpose $remote_job_folder/$box2 $column_select $remote_job_folder/ $permutations $method_select > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
        
        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }
    
    /**
     * Loads job results information related to parallel_anosim.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_anosim_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_anosim";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to parallel_mantel function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function parallel_mantel($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
                                         
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        $box= $form['box']; 
        $box2 = $form['box2'];
        $inputs .= ";".$box2;
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations parameter!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }        
        
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }              
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        $script_source = app_path().'/rvlab/files/mantelMPI_24_09_2015.r';
        copy($script_source,"$job_folder/".$job_id.".R");       

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box FALSE $remote_job_folder/$box2 FALSE $remote_job_folder/ $method_select $permutations > $remote_job_folder/cmd_line_output.txt\n");        
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true; 
                                                          			
    }
    
    /**
     * Loads job results information related to parallel_mantel.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_mantel_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_mantel";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to parallel_taxa2taxon function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function parallel_taxa2taxon($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){
        
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        $box= $form['box']; 
        $box2 = $form['box2']; 
        $inputs .= ";".$box2;       
        
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }  

        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        } 
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        $script_source = app_path().'/rvlab/files/taxa2dist_taxondive.r';
        copy($script_source,"$job_folder/".$job_id.".R");       

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box TRUE $remote_job_folder/$box2 TRUE 39728447488 $remote_job_folder/ $varstep > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true; 
    }
    
    /**
     * Loads job results information related to parallel_taxa2taxon.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_taxa2taxon_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_taxa2taxon";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array('parallelTaxTaxOnPlot.png');
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to parallel_permanova function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function parallel_permanova($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace){
        
        $box = $form['box'];
        
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select a factor file!'));
            return false;
        } else {
            $box2=$form['box2'];
        }

        if(empty($form['transpose'])){
            $transpose = "FALSE";
        } else {
            $transpose = "TRUE";
        }        
        
        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select the Factor1 column!'));
            return false;
        } else {
            $column_select=$form['column_select'];
        }
        
        if(empty($form['column_select2'])){
            Session::flash('toastr',array('error','You forgot to select the Factor2 column!'));
            return false;
        } else {
            $column_select2=$form['column_select2'];
        }
        
        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations!'));
            return false;
        } else {
            $permutations=$form['permutations'];
        }
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to select method!'));
            return false;
        } else {
            $method_select=$form['method_select'];
        }
        
        if(empty($form['single_or_multi'])){
            Session::flash('toastr',array('error','You forgot to select between single or multiple parameter!'));
            return false;
        } else {
            if($form['single_or_multi'] == 'single')
                $single_or_multi = 1;
            else
                $single_or_multi = 2;
        }
		
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }          

        // Build the R script
        $script_source = app_path().'/rvlab/files/permanovaMPI_24_09_2015.r';
        copy($script_source,"$job_folder/".$job_id.".R");       

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box $transpose $remote_job_folder/$box2 $single_or_multi $column_select $column_select2 $remote_job_folder/ $permutations $method_select > $remote_job_folder/cmd_line_output.txt\n"); 
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true; 
    }
	
    /**
     * Loads job results information related to parallel_permanova.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_permanova_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_permanova";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to bioenv function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function bioenv($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs){       

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        if(empty($form['upto'])){
            Session::flash('toastr',array('error','You forgot to set the upto parameter!'));
            return false;
        } else {
            $upto = $form['upto'];
        }
        
        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }
        
        if(empty($form['index'])){
            Session::flash('toastr',array('error','You forgot to set the index parameter!'));
            return false;
        } else {
            $index = $form['index'];
        } 
        
        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        } 
        
        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        } 
        
        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }
        
        $box= $form['box']; 
        $box2 = $form['box2'];  
        $inputs .= ";".$box2;
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file: $job_folder/$job_id.R");
        
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");
        
        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }
        
        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }
        
        fwrite($fh, "otu.ENVFACT.bioenv <- bioenv(mat,ENV,method= \"$method_select\",index = \"$index\",upto=$upto,trace=$trace);\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.bioenv\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
        
    }
    
    /**
     * Loads job results information related to bioenv.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function bioenv_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "bioenv";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to simper function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function simper($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }
        
        $box= $form['box']; 
        $box2 = $form['box2'];          
        if(!empty($form['transpose']))
            $transpose = $form['transpose'];
        else
            $transpose = "";
        $column_select = $form['column_select'];
        $permutations = $form['permutations'];
        $trace = $form['trace'];       
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }        
        
        fwrite($fh, "otu.ENVFACT.simper <- simper(mat,ENV\$$column_select,permutations = $permutations,trace = $trace);\n");        
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.simper\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");
        
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);        

        $this->log_event("/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n","error");
        
        
        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");        
        return true;
    }
    
    /**
     * Loads job results information related to simper.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function simper_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "simper";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');         
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }
    
    /**
     * Handles the part of job submission functionlity that relates to convert2r function.
     * 
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @return boolean
     */
    private function convert2r($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace){

        $box= $form['box'];          
        
        if(empty($form['header1_id'])){
            Session::flash('toastr',array('error','You forgot to set the header1 parameter!'));
            return false;
        } else {
            $header1 = $form['header1_id'];
        }
        
        if(empty($form['header2_id'])){
            Session::flash('toastr',array('error','You forgot to set the header2 parameter!'));
            return false;
        } else {
            $header2 = $form['header2_id'];
        }
        
        if(empty($form['header3_id'])){
            Session::flash('toastr',array('error','You forgot to set the header3 parameter!'));
            return false;
        } else {
            $header3 = $form['header3_id'];
        }
        
        if(empty($form['header1_fact'])){
            Session::flash('toastr',array('error','You forgot to set the factor header1 parameter!'));
            return false;
        } else {
            $header1_fact = $form['header1_fact'];
        }
        
        if(empty($form['header2_fact'])){
            Session::flash('toastr',array('error','You forgot to set the factor header2 parameter!'));
            return false;
        } else {
            $header2_fact = $form['header2_fact'];
        }
        
        if(empty($form['header3_fact'])){
            Session::flash('toastr',array('error','You forgot to set the factor header3 parameter!'));
            return false;
        } else {
            $header3_fact = $form['header3_fact'];
        }
        
        if(empty($form['function_to_run'])){
            Session::flash('toastr',array('error','You forgot to set the function you want to run!'));
            return false;
        } else {
            $function_to_run = $form['function_to_run'];
        }
        
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }
        
        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))  
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(reshape);\n");
        fwrite($fh, "geo <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\");\n");
        fwrite($fh, "write.table(geo, file = \"$remote_job_folder/transformed_dataAbu.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fwrite($fh, "geoabu<-cast(geo, $header1~$header2, $function_to_run, value=\"$header3\");\n");
        fwrite($fh, "write.table(geoabu, file = \"$remote_job_folder/transformed_dataAbu.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fwrite($fh, "geofact = data.frame(geo$$header1_fact,geo$$header2_fact,geo$$header3_fact);\n");
        fwrite($fh, "names(geofact) <- c(\"$header1_fact\",\"$header2_fact\",\"$header3_fact\");\n");
        fwrite($fh, "geofact <- subset(geofact, !duplicated(geofact$$header1_fact));\n");
        fwrite($fh, "rownames(geofact) <- NULL;\n");
        fwrite($fh, "write.table(geofact, file = \"$remote_job_folder/transformed_dataFact.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fclose($fh); 

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))  
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");    
        return true;
                   
    }
    
    /**
     * Loads job results information related to convert2r.
     * 
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function convert2r_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "convert2r";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;
        
        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');        
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            } 
        }
        
        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "transformed_dataAbu";
        $data['blue_disk_extension'] = '.csv';  
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        } 
    }    
    
}
