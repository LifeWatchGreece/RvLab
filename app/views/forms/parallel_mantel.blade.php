<?php $function = "parallel_mantel"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_mantel_form','style'=>'display:none')) }}        

<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('parallel_mantel-box','Select a dissimilarity structure as produced by dist from loaded files',$tooltips,$workspace_files) }}
    {{ form_checkbox('parallel_mantel-transpose1','Check to transpose matrix','transpose',true,$tooltips) }}               
    {{ form_radio_files('parallel_mantel-box2','Select a dissimilarity structure as produced by dist',$tooltips,$workspace_files) }}     
    {{ form_checkbox('parallel_mantel-transpose2','Check to transpose matrix','transpose',true,$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('parallel_mantel-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2',$tooltips) }}            
    {{ form_textinput('parallel_mantel-permutations','Permutations','999',$tooltips) }}    
    {{ form_dropdown('parallel_mantel-method_select','Method:',array('pearson','spearman','canberra'),'spearman',$tooltips) }}         
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}