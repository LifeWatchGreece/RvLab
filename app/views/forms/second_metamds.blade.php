<?php $function = "second_metamds"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'second_metamds_form','style'=>'display:none')) }}        


<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_checkbox_files('second_metamds-box','Select community data file(s) from loaded files',$tooltips,$workspace_files) }}    
    {{ form_dropdown('second_metamds-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}                                           
    {{ form_checkbox('second_metamds-transpose','Check to transpose matrix','transpose',false,$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('second_metamds-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}               
    {{ form_textinput('second_metamds-k_select','K','2',$tooltips) }}
    {{ form_textinput('second_metamds-trymax','trymax','20',$tooltips) }}
    {{ form_dropdown('second_metamds-autotransform_select','autotransform',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_textinput('second_metamds-noshare','noshare','0.1',$tooltips) }}
    {{ form_dropdown('second_metamds-wascores_select','wascores',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_dropdown('second_metamds-expand','expand',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_textinput('second_metamds-trace','trace','1',$tooltips) }} 
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}