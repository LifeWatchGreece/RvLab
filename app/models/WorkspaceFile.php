<?php

class WorkspaceFile extends Eloquent {	
    
    protected $table = 'workspace_files';
    public $timestamps = false;
    
    static function getUserFiles($user_email){
        $results = DB::table('workspace_files')
                    ->where('user_email',$user_email)
                    ->orderBy('filename')
                    ->get();                  
        return $results;
    }
    
}
