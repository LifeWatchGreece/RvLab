<?php $function = "taxa2dist"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'taxa2dist_form')) }}               

    {{ form_function_about('taxa2dist',$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('taxa2dist-box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files',$tooltips,$workspace_files) }}
        
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('taxa2dist-varstep','varstep',array('FALSE','TRUE'),'FALSE',$tooltips) }}
    {{ form_dropdown('taxa2dist-check_taxa2dist','check_taxa2dist',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}