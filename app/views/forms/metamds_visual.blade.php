<?php $function = "metamds_visual"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'metamds_visual_form','style'=>'display:none')) }}        

<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('metamds_visual-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) }}    
    {{ form_dropdown('metamds_visual-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'none',$tooltips) }}                                 
    
    <div style="text-align: right; margin-bottom: 5px">
        <a href="http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/decostand.html" style="color: gray" target="_blank">* info about transformation methods</a>
    </div>    
    
    {{ form_checkbox('metamds_visual-transpose','Check to transpose matrix','transpose',true,$tooltips) }}           
    <br>
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_textinput('metamds_visual-top_species','Number of top ranked species','21',$tooltips) }}
    {{ form_dropdown('metamds_visual-method_select_viz','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}        
    {{ form_textinput('metamds_visual-k_select_viz','K','12',$tooltips) }}
    {{ form_textinput('metamds_visual-trymax_viz','trymax_viz','20',$tooltips) }}
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}