<?php $function = "metamds"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'metamds_form','style'=>'display:none')) }}        

{{ form_function_about('metamds',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('metamds-box','Select community data as a symmetric square matrix from loaded files',$tooltips,$workspace_files) }}
    {{ form_dropdown('metamds-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}                                           
    {{ form_checkbox('metamds-transpose','Check to transpose matrix','transpose',true,$tooltips) }}
    {{ form_radio_files('metamds-box2','Select factor file (Optional)',$tooltips,$workspace_files) }}     
    {{ form_dropdown('metamds-column_select','Select Column in Factor File:',array(),'',$tooltips) }}

    <div style="color: blue; font-weight: bold">Parameters</div>
    
    {{ form_dropdown('metamds-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}             
    {{ form_textinput('metamds-k_select','K','12',$tooltips) }}
    {{ form_textinput('metamds-trymax','trymax','20',$tooltips) }}
    {{ form_dropdown('metamds-autotransform_select','autotransform',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_textinput('metamds-noshare','noshare','0.1',$tooltips) }}
    {{ form_dropdown('metamds-wascores_select','wascores',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_dropdown('metamds-expand','expand',array('FALSE','TRUE'),'TRUE',$tooltips) }}
    {{ form_textinput('metamds-trace','trace','1',$tooltips) }}    
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', '#metamds_form input[name="box2"]', function(){
        var selectedValue = $("#metamds_form input[name='box2']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadCsvHeaders(selectedValue,"metamds_form","column_select");          
    });
    
</script>                    

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','metamds_form','column_select');</script>";
        }
        $count++;
    }        
?>