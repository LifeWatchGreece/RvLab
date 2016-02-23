<?php $function = "parallel_anosim"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_anosim_form','style'=>'display:none')) }}       

<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('parallel_anosim-box','Select community data file from loaded files',$tooltips,$workspace_files) }}                 
    {{ form_checkbox('parallel_anosim-transpose','Check to transpose matrix','transpose',true,$tooltips) }}
    {{ form_radio_files('parallel_anosim-box2','Select factor file',$tooltips,$workspace_files) }}  
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('parallel_anosim-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2',$tooltips) }}    
    {{ form_textinput('parallel_anosim-column_select','Select Column in Factor File:','1',$tooltips) }}   
    {{ form_textinput('parallel_anosim-permutations','Permutations:','999',$tooltips) }}    
    {{ form_dropdown('parallel_anosim-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }} 
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}