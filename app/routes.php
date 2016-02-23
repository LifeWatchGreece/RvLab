<?php

// Home Page
Route::get('/',array('uses'=>'VisitorController@index'));
Route::get('/logout',array('uses'=>'AuthController@logout'));
Route::post('/login',array('uses'=>'VisitorController@login'));

// Job page
Route::get('/job/{job_id}',array('uses'=>'JobController@job_page'));
// New job submission
Route::post('/job',array('before'=>'csrf','uses'=>'JobController@submit'));
// Delete a Job
Route::post('/job/delete/{job_id}',array('uses'=>'JobController@delete_job'));
Route::post('/job/delete_many',array('uses'=>'JobController@delete_many_jobs'));
// Get a file
Route::get('/storage/get_job_file/job/{job_id}/{filename}',array('uses'=>'JobController@get_job_file'));
// Get R script
Route::get('/storage/get_r_script/{job_id}',array('uses'=>'JobController@get_r_script'));
// Get user jobs status (to be called periodically)
Route::get('/get_user_jobs',array('uses'=>'JobController@get_user_jobs'));
// Get single job status (to be called periodically)
Route::get('/get_job_status/{job_id}',array('uses'=>'JobController@get_job_status'));

// Workspace Routes
Route::get('/workspace/get/{filename}',array('uses'=>'WorkspaceController@get_file'));
Route::get('/workspace/manage',array('uses'=>'WorkspaceController@manage'));
Route::post('/workspace/add_files',array('uses'=>'WorkspaceController@add_files'));
Route::post('/workspace/remove_file',array('uses'=>'WorkspaceController@remove_file'));
Route::post('/workspace/remove_files',array('uses'=>'WorkspaceController@remove_files'));
Route::post('/workspace/add_output_file',array('uses'=>'WorkspaceController@add_output_file'));
Route::post('/workspace/add_example_data',array('uses'=>'WorkspaceController@add_example_data'));

Route::get('/workspace/convert2r/{filename}',array('uses'=>'WorkspaceController@convert2r_tool')); 
Route::post('/workspace/tab_status',array('uses'=>'WorkspaceController@change_tab_status'));

// Help Routes
Route::get('/help/storage_policy',array('uses'=>'HelpController@policy'));
Route::get('/help/technical_documentation',array('uses'=>'HelpController@technical_docs'));

// Administrative Routes 
Route::get('/admin',array('uses'=>'AdminController@index'));
Route::get('/admin/job_list',array('uses'=>'AdminController@job_list'));
Route::get('/admin/last_errors',array('uses'=>'AdminController@last_errors'));
Route::get('/admin/storage_utilization',array('uses'=>'AdminController@storage_utilization'));
Route::get('/admin/statistics',array('uses'=>'AdminController@statistics'));
Route::get('/admin/configure',array('uses'=>'AdminController@configure'));
Route::post('admin/configure',array('before'=>'csrf','uses'=>'AdminController@save_configuration'));

// Registration
Route::get('/is_registered/{user_email}',array('uses'=>'RegistrationController@is_registered'));
Route::get('/registration',array('uses'=>'RegistrationController@registration_page'));
Route::post('/registration',array('uses'=>'RegistrationController@register'));

// Mobile
Route::get('/get_token',array('uses'=>'AuthController@get_token'));
Route::get('/mobile/forms/{function}',array('uses'=>'MobileController@forms'));

