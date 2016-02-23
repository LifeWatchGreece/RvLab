<style>
#js_wrapper {
    font: verdana;
    width:1070px;
    margin:1px;
}
path {  stroke: #fff; }
path:hover {  opacity:0.6; }
rect:hover {  opacity:0.6; }
.axis {  font: 10px verdana; }
.legend tr{    border-bottom:1px solid grey; }
.legend tr:first-child{    border-top:1px solid grey; }

.axis path,
.axis line {
  fill: none;
  stroke: #000;
  shape-rendering: crispEdges;
}

.legend{
    font: verdana;
    margin-bottom:10px;
    display:inline-block;
    border-collapse: collapse;
    border-spacing: 0px;
    font-size:16px;	

}
.legend td{
    font: verdana;
    padding: 0px 2px;
    vertical-align:bottom;
    font-size:16px;
}
.legendFreq, .legendPerc{
    font: verdana;
    align:right;
    width:50px;

}
.MDStooltip {
  position: absolute;
  width: 200px;
  height: 28px;
  pointer-events: none;
}

.dot {
  stroke-width: 0;
}


</style>
<div id='js_wrapper'>
<h1>SUMMARIZEplot</h1>
<p>This graph is an adapted version of the original visualation available at:<br>
        <a target="blank" href="http://userweb.eng.gla.ac.uk/umer.ijaz/bioinformatics/summarize_v0.2/summarize.html">
            http://userweb.eng.gla.ac.uk/umer.ijaz/bioinformatics/summarize_v0.2/summarize.html
        </a>
    </p>
    <p>It is developed by Dr. Umer Ijaz, 
        (<a target="blank" href="http://userweb.eng.gla.ac.uk/umer.ijaz/">
            http://userweb.eng.gla.ac.uk/umer.ijaz/
        </a>). <br>
        Please address any visualization questions to: <i>Umer_DOT_Ijaz_AT_glasgow_DOT_ac_DOT_uk</i>
    </p>
<font size=2 face=verdana color='red'>Hint:</font><font size=2 face=verdana> Clicking on the bars loads proportion of terms for a particular sample on the pie chart, selects that sample on the MDS plot and maintains the history for selection, where as clicking on the pie chart slices loads distribution of particular term on the bar plot. Double-clicking on the pie chart displays the stacked bar plot and removes all the selected samples from the MDS plot.</font>
<div id='dashboard'>
</div>
<script src="{{ asset('js/d3.v3.min.js') }}"></script>
<script>
function dashboard(id, fData){
    var barColor = 'white';
    var defColor=["#F0A3FF", "#0075DC", "#993F00","#4C005C","#2BCE48","#FFCC99","#808080","#94FFB5","#8F7C00","#9DCC00","#C20088","#003380","#FFA405","#FFA8BB","#426600","#FF0010","#5EF1F2","#00998F","#740AFF","#990000","#FFFF00"];    

    var colID=0;
    var colorMap={}; //colorMap will contain the color
    var termsList=[]; //termsList will contain the terms
    for (var key in fData[0].freq){
	termsList.push(key);
	colorMap[key]=defColor[colID];
	colID++;
    }	

    // compute total for each samples.
    fData.forEach(function(d){
		var sum=0;
		for (i=0;i<termsList.length;i++){
			sum+=d.freq[termsList[i]];
			}
		d.total=sum;
		});	
    
    // function to handle histogram.
    function histoGram(fD){
        var hG={},    hGDim = {t: 20, r: 0, b: 150, l: 0};
        hGDim.w = 1070 - hGDim.l - hGDim.r, 
        hGDim.h = 500 - hGDim.t - hGDim.b;
            
        //create svg for histogram.
        var hGsvg = d3.select(id).append("svg")
            .attr("width", hGDim.w + hGDim.l + hGDim.r)
            .attr("height", hGDim.h + hGDim.t + hGDim.b).append("g")
            .attr("transform", "translate(" + hGDim.l + "," + hGDim.t + ")");

        // create function for x-axis mapping.
        var x = d3.scale.ordinal().rangeRoundBands([0, hGDim.w], 0.1)
                .domain(fD.map(function(d) { return d[0]; }));

        // Add x-axis to the histogram svg.
        hGsvg.append("g").attr("class", "x axis")
            .attr("transform", "translate(0," + (hGDim.h) + ")")
            .call(d3.svg.axis().scale(x).orient("bottom"))
	    .selectAll("text")  
            .style("text-anchor", "end")
            .attr("dx", "-0.8em")
            .attr("dy", "-.5em")
            .attr("transform", function(d) {
                return "rotate(-90)"
                });

        // Create function for y-axis map.
        var y = d3.scale.linear().range([hGDim.h, 0])
                .domain([0, d3.max(fD, function(d) { return d[1]; })]);

        // Create bars for histogram to contain rectangles and freq labels.
        var bars = hGsvg.selectAll(".bar").data(fD).enter()
                .append("g").attr("class", "bar");
        
        //create the rectangles.
        bars.append("rect")
            .attr("x", function(d) { return x(d[0]); })
            .attr("y", function(d) { return y(d[1]); })
            .attr("width", x.rangeBand())
            .attr("height", function(d) { return hGDim.h - y(d[1]); })
            .attr('fill',barColor)
            .on("click",mouseclick)// mouseclick is defined below.
            .on("dblclick",mousedblclick);// mousedblclick is defined below.
            
        //Create the frequency labels above the rectangles.
        bars.append("text").text(function(d){ return d3.format(",.2f")(d[1])})
            .attr("x", function(d) { return x(d[0])+x.rangeBand()/2; })
            .attr("y", function(d) { return y(d[1])-5; })
	    .attr("font-size", "10px")
            .attr("text-anchor", "middle");


	// create function to display stacked barplots.
	hG.init_stackedbar=function(){

        var stackedBars=hGsvg.selectAll(".stackedbar")
                .data(fData).enter()
                .append("g").attr("id","removeSTACKBAR");

	//Create stacked rects
	var stackedRects = stackedBars
		.selectAll(".stackedrect")
		.data(function(d) {
			var total_freq=0;
			var rectArray=[];
			for (val in termsList){total_freq+=d.freq[termsList[val]];}
			total=0;
			for(val in termsList){
				rectArray.push([d.Samples,termsList[val],total,d.freq[termsList[val]]+total,total_freq]);
				total+=d.freq[termsList[val]];				
				}
			return rectArray;
		}).enter().append("rect").attr("id","removerect")
		.attr("x",function(d){ return x(d[0]);})
		.attr("y",function(d){ return y(d[4]-d[2]);})
		.attr("width",x.rangeBand())
		.attr("height",function(d){ return y(d[2]-d[3])-y(0);})
		.attr("fill",function(d){return colorMap[d[1]];})
		.on("click",mouseclick)
		.on("dblclick",mousedblclick);
	}
	hG.init_stackedbar();

 
        function mouseclick(d){  // utility function to be called on mouseclick.
            // filter for selected samples.
            var st = fData.filter(function(s){ return s.Samples == d[0];})[0],
                nD = d3.keys(st.freq).map(function(s){ return {type:s, freq:st.freq[s]};});
            // call update functions of pie-chart and legend.    
   	    d3.select("#statustext").html("<table><font size=2 face='verdana' color='red'>Pie chart: </font><font size=2 face='verdana'>"+d[0]+"</font></table>");	

	    // highlight this sample in MDS plot	
	    md.update(null,d[0]);
            pC.update(nD);
            leg.update(nD);
        }
        
        function mousedblclick(d){    // utility function to be called on mousedblclick.
            // reset the pie-chart and legend.    
	    pC.update(tF);
            leg.update(tF);
	    d3.select("#statustext").html("<table><font size=2 face='verdana' color='red'>Pie chart: </font><font size=2 face='verdana'>Overall</font></table>");
       
	    // refresh MDS plot
            var mdp=fData.map(function(d){return [d.Samples,d.MDS.MDS1,d.MDS.MDS2];});
	    d3.selectAll(".dot").remove(); 
	    md.update(mdp,null);	
	 }
        
        // create function to update the bars. This will be used by pie-chart.
        hG.update = function(nD, color){
	    	 
	    // update the domain of the y-axis map to reflect change in frequencies.
            y.domain([0, d3.max(nD, function(d) { return d[1]; })]);
            
            // Attach the new data to the bars.
            var bars = hGsvg.selectAll(".bar").data(nD);
            
            // transition the height and color of rectangles.
            bars.select("rect").transition().duration(500)
                .attr("y", function(d) {return y(d[1]); })
                .attr("height", function(d) { return hGDim.h - y(d[1]); })
                .attr("fill", color);

            // transition the frequency labels location and change value.
            bars.select("text").transition().duration(500)
                .text(function(d){ return d3.format(",.2f")(d[1])})
                .attr("y", function(d) {return y(d[1])-5; });            
	   
           hGsvg.selectAll("#removeSTACKBAR").remove();


      }        
        return hG;
    }
    
    // function to handle pieChart.
    function pieChart(pD){
        var pC ={},    pieDim ={w:500, h: 500};
        pieDim.r = Math.min(pieDim.w, pieDim.h) / 2;
                
        // create svg for pie chart.
        var piesvg = d3.select(id).append("svg")
            .attr("width", pieDim.w).attr("height", pieDim.h).append("g")
            .attr("transform", "translate("+pieDim.w/2+","+pieDim.h/2+")");
        
        // create function to draw the arcs of the pie slices.
        var arc = d3.svg.arc().outerRadius(pieDim.r - 10).innerRadius(0);

        // create a function to compute the pie slice angles.
        var pie = d3.layout.pie().sort(null).value(function(d) { return d.freq; });

        // Draw the pie slices.
        piesvg.selectAll("path").data(pie(pD)).enter().append("path").attr("d", arc)
            .each(function(d) { this._current = d; })
	    .style("fill", function(d) { return colorMap[d.data.type]; })
            .on("click",mouseclick).on("dblclick",mousedblclick);

        // create function to update pie-chart. This will be used by histogram.
        pC.update = function(nD){
            piesvg.selectAll("path").data(pie(nD)).transition().duration(500)
                .attrTween("d", arcTween);
        }        
        // Utility function to be called on mouseclick a pie slice.
        function mouseclick(d){
            // call the update function of histogram with new data.
            hG.update(fData.map(function(v){ 
		return [v.Samples,v.freq[d.data.type]];}),colorMap[d.data.type]);
        }
        //Utility function to be called on mousedblclick a pie slice.
        function mousedblclick(d){
            // call the update function of histogram with all data.
            hG.update(fData.map(function(v){
                return [v.Samples,v.total];}), barColor);
	    hG.init_stackedbar();
	
            // refresh MDS plot
            var mdp=fData.map(function(d){return [d.Samples,d.MDS.MDS1,d.MDS.MDS2];});
	    d3.selectAll(".dot").remove();    
            md.update(mdp,null);
	
		
        }
        // Animating the pie-slice requiring a custom function which specifies
        // how the intermediate paths should be drawn.
        function arcTween(a) {
            var i = d3.interpolate(this._current, a);
            this._current = i(0);
            return function(t) { return arc(i(t));    };
        }    
        return pC;
    }
    
    // function to handle legend.
    function legend(lD){
        var leg = {};
            
        // create table for legend.
        var legend = d3.select(id).append("table").attr('class','legend');
        
        // create one row per segment.
        var tr = legend.append("tbody").selectAll("tr").data(lD).enter().append("tr");
            
        // create the first column for each segment.
        tr.append("td").append("svg").attr("width", '16').attr("height", '16').append("rect")
            .attr("width", '16').attr("height", '16')
			.attr("fill",function(d){ return colorMap[d.type]; });
            
        // create the second column for each segment.
        tr.append("td").text(function(d){ return d.type;});

        // create the third column for each segment.
        tr.append("td").attr("class",'legendFreq')
            .text(function(d){ return d3.format(",.2f")(d.freq);});

        // create the fourth column for each segment.
        tr.append("td").attr("class",'legendPerc')
            .text(function(d){ return getLegend(d,lD);});

        // Utility function to be used to update the legend.
        leg.update = function(nD){
            // update the data attached to the row elements.
            var l = legend.select("tbody").selectAll("tr").data(nD);

            // update the frequencies.
            l.select(".legendFreq").text(function(d){ return d3.format(",.2f")(d.freq);});

            // update the percentage column.
            l.select(".legendPerc").text(function(d){ return getLegend(d,nD);});        
        }
        
        function getLegend(d,aD){ // Utility function to compute percentage.
            return d3.format("%")(d.freq/d3.sum(aD.map(function(v){ return v.freq; })));
        }

        return leg;
    }

    // function to handle MDS.
    function MDS(mD){
    	var md={}, mDSDim ={t: 0, r: 40, b: 50, l: 50};
    	mDSDim.w = 550 - mDSDim.l - mDSDim.r,
    	mDSDim.h = 550 - mDSDim.t - mDSDim.b;
	
    	// setup MDS1
    	var xValue = function(d) { return d[1];}; // data -> value
    	xScale = d3.scale.linear().range([0, mDSDim.w]); // value -> display
    	xMap = function(d) { return xScale(xValue(d));}; // data -> display
    	xAxis = d3.svg.axis().scale(xScale).orient("bottom");

   	// setup MDS2
    	var yValue = function(d) { return d[2];}; // data -> value
    	yScale = d3.scale.linear().range([mDSDim.h, 0]); // value -> display
    	yMap = function(d) { return yScale(yValue(d));}; // data -> display
    	yAxis = d3.svg.axis().scale(yScale).orient("left");


    	// create svg for MDS.
    	var mdssvg = d3.select(id).append("svg")
    		.attr("width", mDSDim.w + mDSDim.l + mDSDim.r)
    		.attr("height",mDSDim.h + mDSDim.t + mDSDim.b)
  		.append("g")
    		.attr("transform", "translate(" + mDSDim.l + "," + mDSDim.t + ")");	

    	// add the MDS tooltip area to the webpage
    	var tooltip = d3.select(id).append("div")
    		.attr("class", "MDStooltip")
    		.style("opacity", 0);	

    	// don't want dots overlapping axis, so add in buffer to data domain
    	xScale.domain([d3.min(mD, xValue)-0.25, d3.max(mD, xValue)+0.25]);
    	yScale.domain([d3.min(mD, yValue)-0.25, d3.max(mD, yValue)+0.25]);

   	// x-axis
   	mdssvg.append("g")
      		.attr("class", "x axis")
      		.attr("transform", "translate(0," + mDSDim.h + ")")
      		.call(xAxis)
    		.append("text")
      		.attr("class", "label")
      		.attr("x", mDSDim.w)
      		.attr("y",-6)
      		.style("text-anchor", "end")
      		.text("MDS1");

   	// y-axis
   	mdssvg.append("g")
      		.attr("class", "y axis")
      		.call(yAxis)
    		.append("text")
      		.attr("class", "label")
      		.attr("transform", "rotate(-90)")
      		.attr("y", 6)
      		.attr("dy", ".71em")
      		.style("text-anchor", "end")
      		.text("MDS2");

 
    	// create function to update the MDS plot.
     	md.update = function(mD, sample){
		if(sample==null){
			// draw dots
				mdssvg.selectAll(".dot")
      				.data(mD)
    				.enter().append("circle")
      				.attr("id", function (d) { return "MDS_" + d[0]; })
      				.attr("class", "dot")
      				.attr("r", 3.5)
      				.attr("cx", xMap)
      				.attr("cy", yMap)
      				.style("fill", "orange")
      				.attr('fill-opacity', 0.9)
      				.on("mouseover", function(d) {
          				tooltip.transition()
               					.duration(200)
               					.style("opacity", .9);
          				tooltip.html(d[0])
               					.style("left", (d3.event.pageX + 5) + "px")
               					.style("top", (d3.event.pageY - 28) + "px");
      				})
      				.on("mouseout", function(d) {
          				tooltip.transition()
               					.duration(500)
               					.style("opacity", 0);
      				});
		}
		else{
			d3.select("#MDS_"+sample)
                		.transition(500)
                		.style("fill","green")
                		.attr("r", 18)
                		.transition(500)
                		.attr("r",6);
		}
 
     	}
    	md.update(mD,null);
    	return md;
    } 

    // calculate total frequency by segment for all samples.	
    var tF = termsList.map(function(d){  
        return {type:d, freq: d3.sum(fData.map(function(t){ return t.freq[d];}))};
    });
    		
    // calculate total frequency by samples for all segment.
    var sF = fData.map(function(d){return [d.Samples,d.total];});

    // calculate MDS for all samples	
    var mDS = fData.map(function(d){return [d.Samples,d.MDS.MDS1,d.MDS.MDS2];});	




    var hG,pC,leg,md;	
    hG = histoGram(sF); // create the histogram.
    d3.select(id).append("text").attr("id","statustext").html("<table cellpadding=0 cellspacing=0><tr><td><font size=2 face='verdana' color='red'>Pie chart: </font><font size=2 face='verdana'>Overall</font></td></table>"); //create text	
 	
    pC = pieChart(tF); // create the pie-chart.
    leg= legend(tF);  // create the legend.
    md = MDS(mDS); //create the MDS plot.

}
</script>

<script type="text/javascript">
@foreach($data_js as $line)
    {{ $line }}
@endforeach
</script>
<script>
dashboard('#dashboard',freqData);
d3.select("#dashboard").style('height','auto')
      .style('background-color','#E8E8E8')
      .style('top',0)
      .style('padding',10)
      .style('border-radius','10px')
      .style('-webkit-border-radius','10px')
      .style('-moz-border-radius','10px')
      .style('-webkit-box-shadow','4px 4px 10px rgba(0, 0, 0, 0.4)')
      .style('-moz-box-shadow','4px 4px 10px rgba(0, 0, 0, 0.4)')
      .style('box-shadow','4px 4px 10px rgba(0, 0, 0, 0.4)')
      .style('left',40)
      .style('display','inline-block');	

</script>
</div>
