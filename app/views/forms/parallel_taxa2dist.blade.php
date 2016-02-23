<?php $function = "parallel_taxa2dist"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_taxa2dist_form','style'=>'display:none')) }}               

    <br>
    <div style="color: blue; font-weight: bold">Input files</div>
    
    {{ form_radio_files('parallel_taxa2dist-box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files',$tooltips,$workspace_files) }}     
   
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('parallel_taxa2dist-varstep','varstep',array('FALSE','TRUE'),'FALSE',$tooltips) }}
    {{ form_dropdown('parallel_taxa2dist-check_parallel_taxa2dist','check',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_dropdown('parallel_taxa2dist-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10','11','12'),'2',$tooltips) }}    
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}