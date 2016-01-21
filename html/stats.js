
function run_stats() {

  for(var i=0;i<stats['categories'].length;i++) {
    var s = stats['categories'][i];
    if(s.label=='EX') {
      s.color='#000000';
    }else if(s.label=='EW') {
      s.color='#330000';
    }else if(s.label=='CR') {
      s.color='#663333';
    }else if(s.label=='EN') {
      s.color='#aa6666';
    }else if(s.label=='VU') {
      s.color='#ccaaaa';
    } else if(s.label=='NT') {
      s.color='#cccc66';
    } else if(s.label=='LC') {
      s.color='#aaaacc';
    } else if(s.label=='DD') {
      s.color='#aaaaaa';
    }
  }
  var cats_canvas=document.querySelector('.cats canvas');
  var cats_chart=new Chart(cats_canvas.getContext('2d')).Pie(stats['categories'],{animationEasing:'linear',animationSteps:30});
  cats_canvas.onclick=function(e) {
    var segs = cats_chart.getSegmentsAtEvent(e);
    if(segs.length >= 1) {
      var val = segs[0].label;
      var query = 'risk-assessment.category:"'+val+'"';
      document.querySelector('form input').value=query;
      document.querySelector('form').setAttribute('target','_blank');
      document.querySelector('form').submit();
    }
  }


  var occs_points=[
    {label:'Occurrences',value:stats.occs_count - stats.points_count,color:'#aaaaaa' },
    {label:'Points',value:stats.points_count,color:'#aaaacc' }
  ];
  var occs_points_canvas=document.querySelector('.occs_points canvas');
  var occs_points_chart=new Chart(occs_points_canvas.getContext('2d')).Pie(occs_points,{animationEasing:'linear',animationSteps:30});
  occs_points_canvas.onclick=function(e) {
    var segs = occs_points_chart.getSegmentsAtEvent(e);
    if(segs.length >= 1) {
      var val = segs[0].label;
      var query = '';
      console.log(val);
      if(val=='Points'){
        query+='points.count:>0';
      } else {
        query+='points.count:0';
      }
      document.querySelector('form input').value=query;
      document.querySelector('form').setAttribute('target','_blank');
      document.querySelector('form').submit();
    }
  }

  var points_ranges={
    labels:[],
    datasets:[
      {label:"Points count range",data:[],fillColor:'#aaaacc'}
    ]
  }

  var points_values=[];
  for(var i=0;i<stats['points_ranges'].length;i++) {
    var s = stats['points_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    points_values[s.value]=s.label;
    points_ranges.labels.push(s.label);
    points_ranges.datasets[0].data.push(s.value);
  }
  var points_canvas=document.querySelector('.points_ranges canvas');
  var points_chart=new Chart(points_canvas.getContext('2d')).Bar(points_ranges,{});
  points_canvas.onclick=function(e) {
    var bars = points_chart.getBarsAtEvent(e);
    if(bars.length >= 1) {
      var val = points_values[bars[0].value];
      var query = 'points.count:';
      if(val =='0') {
        query += '0';
      } else {
        var parts = val.split(' to ');
        query += '(>='+parts[0]+' AND <'+parts[1]+')';
      }
      document.querySelector('form input').value=query;
      document.querySelector('form').setAttribute('target','_blank');
      document.querySelector('form').submit();
    }
  }

  var occs_ranges={
    labels:[],
    datasets:[
      {label:"Occurrences count range",data:[],fillColor:'#aaaaaa'}
    ]
  }

  var occs_values=[];
  for(var i=0;i<stats['occs_ranges'].length;i++) {
    var s = stats['occs_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    occs_values[s.value]=s.label;
    occs_ranges.labels.push(s.label);
    occs_ranges.datasets[0].data.push(s.value);
  }
  var occs_canvas=document.querySelector('.occs_ranges canvas');
  var occs_chart=new Chart(occs_canvas.getContext('2d')).Bar(occs_ranges,{});
  occs_canvas.onclick=function(e) {
    var bars = occs_chart.getBarsAtEvent(e);
    if(bars.length >= 1) {
      var val = occs_values[bars[0].value];
      var query = 'occurrences.count:';
      if(val =='0') {
        query += '0';
      } else {
        var parts = val.split(' to ');
        query += '(>='+parts[0]+' AND <'+parts[1]+')';
      }
      document.querySelector('form').setAttribute('target','_blank');
      document.querySelector('form input').value=query;
      document.querySelector('form').submit();
    }
  }

  var eoo_ranges={
    labels:[],
    datasets:[
      {label:"Extent of occurrence",data:[],fillColor:'#aaaaaa'}
    ]
  }
  var eoo_values={};
  for(var i=0;i<stats['eoo_ranges'].length;i++) {
    var s = stats['eoo_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    eoo_values[s.value]=s.label;
    eoo_ranges.labels.push(s.label);
    eoo_ranges.datasets[0].data.push(s.value);
  }
  var eoo_canvas = document.querySelector('.eoo_ranges canvas');
  var eoo_chart =new Chart(eoo_canvas.getContext('2d')).Bar(eoo_ranges,{});
  eoo_canvas.onclick=function(e) {
    var bars = eoo_chart.getBarsAtEvent(e);
    if(bars.length >= 1) {
      var val = eoo_values[bars[0].value];
      var query = '_type:"eoo" AND area:';
      if(val =='0') {
        query += '0';
      } else {
        var parts = val.split(' to ');
        query += '(>='+parts[0]+' AND <'+parts[1]+')';
      }
      document.querySelector('form input').value=query;
      document.querySelector('form').setAttribute('target','_blank');
      document.querySelector('form').submit();
    }
  }

  var aoo_ranges={
    labels:[],
    datasets:[
      {label:"Area of occupancy",data:[],fillColor:'#aaaacc'}
    ]
  }
  var aoo_values={};
  for(var i=0;i<stats['aoo_ranges'].length;i++) {
    var s = stats['aoo_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    aoo_values[s.value]=s.label;
    aoo_ranges.labels.push(s.label);
    aoo_ranges.datasets[0].data.push(s.value);
  }
  var aoo_canvas = document.querySelector('.aoo_ranges canvas');
  var aoo_chart = new Chart(aoo_canvas.getContext('2d')).Bar(aoo_ranges,{});
  aoo_canvas.onclick=function(e) {
    var bars = aoo_chart.getBarsAtEvent(e);
    if(bars.length >= 1) {
      var val = aoo_values[bars[0].value];
      var query = '_type:"aoo" AND area:';
      if(val =='0') {
        query += '0';
      } else {
        var parts = val.split(' to ');
        query += '(>='+parts[0]+' AND <'+parts[1]+')';
      }
      document.querySelector('form input').value=query;
      document.querySelector('form').setAttribute('target','_blank');
      document.querySelector('form').submit();
    }
  }
};

