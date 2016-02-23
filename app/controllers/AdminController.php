<?php

/**
 * Handles the administration functionality of R vLab
 *
 * @license MIT
 * @author Alexandros Gougousis
 */
class AdminController extends AuthController {
    
    public function __construct() {
        parent::__construct();
        
        if(!$this->is_admin()){
            $this->log_event("Unauthorized access request to /admin","unathorized");        
            echo $this->unauthorizedAccess();
            die();
        }
        
    }
    
    /**
     * Loads the administration control panel
     * 
     * @return View
     */
    public function index(){
        return $this->load_view('admin.index','Admin Pages');
    }
    
    /**
     * Loads the system configuration page
     * 
     * @return View
     */
    public function configure(){

        $settings = Setting::all();
        $data['settings'] = $settings;

        return $this->load_view('admin.configure','System Configuration',$data);
      
    }
    
    /**
     * Saves the new system configuration
     * 
     * @return RedirectResponse
     */
    public function save_configuration(){
        $form = Input::all();   
        if(!empty($form)){
            foreach($form as $key => $value){
                $setting = Setting::where('name',$key)->first();
                if(!empty($setting)){
                    $setting->value = $value;
                    $setting->last_modified = (new DateTime())->format("Y-m-d H:i:s");
                    $setting->save();
                }
            }
        }                               

        return Redirect::to('admin/configure');
    }
    
    /**
     * Loads R vLab usage statistics page
     * 
     * @return View
     */
    public function statistics(){                
        
        /******************************
         *  Registration Statistics
         ******************************/
        $limits = array(
            array('Jan','01-01 00:00:00','01-15 23:59:59'),
            array('Feb','02-01 00:00:00','02-15 23:59:59'),
            array('Mar','03-01 00:00:00','03-15 23:59:59'),
            array('Apr','04-01 00:00:00','04-15 23:59:59'),
            array('May','05-01 00:00:00','05-15 23:59:59'),
            array('Jun','06-01 00:00:00','06-15 23:59:59'),
            array('Jul','07-01 00:00:00','07-15 23:59:59'),
            array('Aug','08-01 00:00:00','08-15 23:59:59'),
            array('Sep','09-01 00:00:00','09-15 23:59:59'),
            array('Oct','10-01 00:00:00','10-15 23:59:59'),
            array('Nov','11-01 00:00:00','11-15 23:59:59'),
            array('Dec','12-01 00:00:00','12-15 23:59:59'),            
        );
        
        $counts = array();
        
        $year = date('Y');
        $prev_year = $year - 1;
        $month = date('n');
        $day = date('j');
        if ($day < 15)
            $month--;
        
        for($j = $month; $j < 12; $j++){
            $checkpoint = $prev_year."-".$limits[$j][2];
            $regs = Registration::where('starts','<=',$checkpoint)
                    ->where('ends','>=',$checkpoint)
                    ->count();
            $counts[] = array($limits[$j][0]." ".$prev_year, $regs);
        }
        
        for($j = 0; $j < $month; $j++){
            $checkpoint = $year."-".$limits[$j][2];
            $regs = Registration::where('starts','<=',$checkpoint)
                    ->where('ends','>=',$checkpoint)
                    ->count();
            $counts[] = array($limits[$j][0]." ".$year, $regs);
        }
        
        /******************************
         *  Functions Statistics
         ******************************/
        
        $dateLimit = "$prev_year-$month-$day 00:00:00";
        
        $f_stats = JobsLog::select(DB::raw('count(*) as total,function'))
                ->where('submitted_at','>',$dateLimit)
                ->groupBy('function')
                ->get()->toArray();        
        
        /******************************
         *  Job size Statistics
         ******************************/
        
        $s_stats = array(
            '1' =>  0,
            '2' =>  0,
            '3' =>  0,
            '4' =>  0,
            '5' =>  0,
            '6' =>  0,
            '7' =>  0,
            '8' =>  0,
            '9' =>  0           
        );
        
        // We just count how many jobs are there with jobsize of x digits
        $size_stats = JobsLog::select(DB::raw('count(*) as total, LENGTH(jobsize) AS digits'))
                ->where('submitted_at','>',$dateLimit)
                ->groupBy('digits')
                ->get()->toArray();  
        
        foreach($size_stats as $stat){
            $s_stats[$stat['digits']] = $stat['total'];
        }
                       
        $data['registration_counts'] = $counts;
        $data['f_stats'] = $f_stats;
        $data['s_stats'] = $s_stats;
        return $this->load_view('admin.statistics','R vLab Usage Statistics',$data);
        
    }
    
    /**
     * Displays the last 50 jobs submitted to R vLab
     * 
     * @return View
     */
    public function job_list(){
        
        $job_list = Job::take(50)->orderBy('submitted_at','desc')->get();
        $data['job_list'] = $job_list;
        return $this->load_view('admin.job_list','Last Jobs List',$data);
        
    }
    
    /**
     * Displays the last 20 errors logged by R vLab
     * 
     * @return View
     */
    public function last_errors(){
        
        $error_list = SystemLog::where('category','error')->orderBy('when','desc')->take(20)->get();
        $data['error_list'] = $error_list;
        return $this->load_view('admin.last_errors','Last errors list',$data);
        
    }
    
    /**
     * Displays storate utilization information for each R vLab user. 
     * 
     * @return View
     */
    public function storage_utilization(){
        
        $rvlab_storage_limit = $this->system_settings['rvlab_storage_limit'];
        $max_users_suported = $this->system_settings['max_users_suported'];          
        $jobs_path = Config::get('rvlab.jobs_path');
        $workspace_path = Config::get('rvlab.$workspace_path');
        
        // Total Storage Utilization
        $jobs_size = directory_size($jobs_path); // in KB    
        $workspace_size = directory_size($workspace_path); // in KB
        $used_size = $jobs_size+$workspace_size; 
        $utilization = 100*$used_size/$rvlab_storage_limit;
        
        // Storage Utilization per User (A - input files)
        $inputs_users = WorkspaceFile::select('user_email')->distinct()->get(); // Get users with a least one input file
        
        $user_totals = array();        
        $inputs_totals = array();
        
        foreach($inputs_users as $user){
            $inputs_totals[$user->user_email] = directory_size($workspace_path.'/'.$user->user_email); // in KB
            $user_totals[$user->user_email] =  $inputs_totals[$user->user_email];
        }
        
        // Storage Utilization per User (B - jobs)
        $rvlab_users = Job::select('user_email')->distinct()->get(); // Get users with a least one job
        $jobspace_totals = array();
        foreach($rvlab_users as $user){                        
            $jobspace_totals[$user->user_email] = directory_size($jobs_path.'/'.$user->user_email); // in KB            
            if(isset($user_totals[$user->user_email])){
                $user_totals[$user->user_email] += $jobspace_totals[$user->user_email];
            } else {
                $user_totals[$user->user_email] = $jobspace_totals[$user->user_email];
            }            
        }       
        
        // Note: $inputs_totals and $jobspace_totals are not used for the moment.
        // If they are not going to be used in the future, we don't need to keep them
        // in separate variables.
        
        $data['rvlab_storage_limit'] = $rvlab_storage_limit;
        $data['max_users_suported'] = $max_users_suported;
        $data['user_totals'] = $user_totals;
        $data['utilized_space'] = $used_size;
        $data['utilization'] = $utilization;
        return $this->load_view('admin.storage_utilization','Storage Utilization',$data);
        
    }
    
}
