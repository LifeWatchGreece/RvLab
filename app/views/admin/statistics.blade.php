<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">

    // Load the Visualization API and the piechart package.
    google.load('visualization', '1.0', {'packages':['corechart']});

    // Set a callback to run when the Google Visualization API is loaded.
    google.setOnLoadCallback(drawChart);

    // Callback that creates and populates a data table,
    // instantiates the pie chart, passes in the data and
    // draws it.
    function drawChart() {

      // Create the data table.
      var r_data = google.visualization.arrayToDataTable([
        ['Time Period', 'Users registered', { role: 'annotation' } ],
        @foreach($registration_counts as $count)
            ['{{ $count[0] }}',{{ $count[1] }},'{{ $count[1] }}'],
        @endforeach
      ]);

      // Set chart options
      var r_options = {
                'title':'Users registered',
                'width':1000,
                'height':400,
                'titleTextStyle': {color: 'gray', fontSize: 20},    // change title style
                legend: { position: "none" },                       // hide legends
                hAxis: {
                    textStyle: { fontSize: 10 }
                }
              };
              
    // Create the data table.
      var f_data = google.visualization.arrayToDataTable([
        ['Function', 'Times used', { role: 'annotation' } ],
        @foreach($f_stats as $stat)
            ["{{ $stat['function'] }}",{{ $stat['total'] }},"{{ $stat['total'] }}"],
        @endforeach
      ]);

      // Set chart options
      var f_options = {
                'title':'Functions usage',
                'width':1000,
                'height':400,
                'titleTextStyle': {color: 'gray', fontSize: 20},    // change title style
                legend: { position: "none" },                       // hide legends
                hAxis: {
                    textStyle: { fontSize: 10 }
                }
              };

    // Create the data table.
    var label = "";
    var s_data = google.visualization.arrayToDataTable([
      ['Job size', 'Times appeared', { role: 'annotation' } ],
      @foreach($s_stats as $digits => $counted)          
          [getDigitsLabel({{ $digits }}),{{ $counted }},"{{ $counted }}"],
      @endforeach
    ]);

      // Set chart options
      var s_options = {
                'title':'Job size statistics',
                'width':1000,
                'height':400,
                'titleTextStyle': {color: 'gray', fontSize: 20},    // change title style
                legend: { position: "none" },                       // hide legends
                hAxis: {
                    textStyle: { fontSize: 10 }
                }
              };

      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.ColumnChart (document.getElementById('registration_chart'));
      chart.draw(r_data, r_options);
      var chart = new google.visualization.ColumnChart (document.getElementById('functions_chart'));
      chart.draw(f_data, f_options);
      var chart = new google.visualization.ColumnChart (document.getElementById('jobsize_chart'));
      chart.draw(s_data, s_options);
    }
    
    function getDigitsLabel(digits){
        var label = "";
        switch(digits){
              case 1:
                  label = '0-10KB';
                  break;
              case 2:
                  label = '10KB-100KB';
                  break;
              case 3:
                  label = '100KB-1MB';
                  break;
              case 4:
                  label = '1MB-10MB';
                  break;
              case 5:
                  label = '10MB-100KB';
                  break;    
              case 6:
                  label = '100MB-1GB';
                  break;    
              case 7:
              case 8:
              case 9:
                  label = '>1GB';
                  break;
          }
          return label;
    }
    
</script>

@include("admin.bar")

<div class="col-sm-12">
    <div id="registration_chart"></div>
    <div id="functions_chart"></div>
    <div id="jobsize_chart"></div>
</div>
    


