<?php $function = "parallel_taxa2taxon"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_taxa2taxon_form','style'=>'display:none')) }}        

{{ form_function_about('parallel_taxa2taxon',$tooltips) }}
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('parallel_taxa2taxon-box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files',$tooltips,$workspace_files) }}         
    {{ form_radio_files('parallel_taxa2taxon-box2','Select community data matrix from loaded files',$tooltips,$workspace_files) }}              
    {{ form_checkbox('parallel_taxa2taxon-transpose','Check to transpose matrix','transpose',false,$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>           
    
    {{ form_dropdown('parallel_taxa2taxon-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2',$tooltips) }}                       
    {{ form_dropdown('parallel_taxa2taxon-varstep','varstep',array('FALSE','TRUE'),'TRUE',$tooltips) }} 
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}