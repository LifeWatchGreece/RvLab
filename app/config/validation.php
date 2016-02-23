<?php

return array(
    'upload_to_workspace'    =>  array(
                        'local_files'   =>  'mimes:csv,txt|max:50000|valid_filename',
                    ),
    'login' =>  array(
        'username'  =>  'required',
        'password'  =>  'required'
    )
);