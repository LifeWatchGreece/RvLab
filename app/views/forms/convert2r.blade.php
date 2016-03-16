<?php $function = "convert2r"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'convert2r_form','style'=>'display:none')) }}       

    {{ form_function_about('convert2r',$tooltips) }}
    <br>
    <div style="color: blue; font-weight: bold">Input files</div>

    <div class='radio_wrapper'>
        <div class='configuration-label'>
            Select data in standard csv format from loaded files
        </div>
        @if(empty($workspace_files))
            <br><span style='color: red'>No files in your workspace!</span>
        @endif
        @foreach($workspace_files as $file)
        <? $count = 0; ?>
            <div class="radio">
                <label>
                    @if($count == 0) <!-- The first file should appear selected -->
                        <input type="radio" name="box" value="{{ $file->filename }}" checked>
                    @else
                        <input type="radio" name="box" value="{{ $file->filename }}">
                    @endif
                    {{ $file->filename }}
                </label>
            </div>
        <? $count++; ?>
        @endforeach
    </div>    

    <div style="color: blue; font-weight: bold">Parameters</div>
    
    <br>
    <div class='configuration-label'>
        <strong>Abunfance File</strong>
        Reshape data to create an abundance file according the following equation: geotransformed<-cast(geo, Header1~Header2, Function_to_run, value=\"Header3\")       
    </div>
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Header 1  
        </div>
        <select name="header1_id" id="header1_id"></select>
    </div>   
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Header 2  
        </div>
        <select name="header2_id" id="header2_id"></select>
    </div>
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Header 3  
        </div>
        <select name="header3_id" id="header3_id"></select>
    </div>
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Function to run  
        </div>
        <select name="function_to_run" id="function_to_run">
            <option>sum</option>
            <option>mean</option>
        </select>
    </div>
    
    <div class='configuration-label'>
        <strong>Factor File</strong>
        create a factor file by selecting from three availbale headers.       
    </div>
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Factor Header 1  
        </div>
        <select name="header1_fact" id="header1_fact"></select>
    </div>
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Factor Header 2  
        </div>
        <select name="header2_fact" id="header2_fact"></select>
    </div>
    
    <div class='select_wrapper'>
        <div class='configuration-label'>
            Factor Header 3  
        </div>
        <select name="header3_fact" id="header3_fact"></select>
    </div>
    
    
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', 'input[name="box"]', function(){
        var selectedValue = $("input[name='box']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadFileHeaders(selectedValue);          
    });
    
</script>
                    
        
<script type="text/javascript">  
    
    function loadFileHeaders(filename){
            $.ajax({
                url: '{{ url("workspace/convert2r") }}'+"/"+filename, 
                type: "GET",
                dataType : 'json',
                success: function(data) {
                    
                    headers = data.headers;
                    var selectElement = $('#header1_id');
                    selectElement.empty();
                    for(var i = 0; i < headers.length; i++) {
                        selectElement.append("<option>"+headers[i]+"</option>");
                    }
                    
                    selectElement = $('#header2_id');
                    selectElement.empty();
                    for(var i = 0; i < headers.length; i++) {
                        selectElement.append("<option>"+headers[i]+"</option>");
                    }
                    
                    selectElement = $('#header3_id');
                    selectElement.empty();
                    for(var i = 0; i < headers.length; i++) {
                        selectElement.append("<option>"+headers[i]+"</option>");
                    }
                    
                    selectElement = $('#header1_fact');
                    selectElement.empty();
                    for(var i = 0; i < headers.length; i++) {
                        selectElement.append("<option>"+headers[i]+"</option>");
                    }
                    
                    selectElement = $('#header2_fact');
                    selectElement.empty();
                    for(var i = 0; i < headers.length; i++) {
                        selectElement.append("<option>"+headers[i]+"</option>");
                    }
                    
                    selectElement = $('#header3_fact');
                    selectElement.empty();
                    for(var i = 0; i < headers.length; i++) {
                        selectElement.append("<option>"+headers[i]+"</option>");
                    }
                    
                },                
                error: function(jqXHR, textStatus, errorThrown) 
                {
                    toastr.error("File headers could not be retrieved!");
                }
            });
    }    
    
</script>       

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadFileHeaders('".$file->filename."');</script>";
        }
        $count++;
    }        
?>