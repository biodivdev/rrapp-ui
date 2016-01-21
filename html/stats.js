
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
  new Chart(document.querySelector('.cats canvas').getContext('2d')).Pie(stats['categories'],{animationEasing:'linear',animationSteps:30});

  var occs_points=[
    {label:'Occurrences',value:stats.occs_count - stats.points_count,color:'#aaaaaa' },
    {label:'Points',value:stats.points_count,color:'#aaaacc' }
  ];
  new Chart(document.querySelector('.occs_points canvas').getContext('2d')).Pie(occs_points,{animationEasing:'linear',animationSteps:30});

  var occs_ranges={
    labels:[],
    datasets:[
      {label:"Occurrences count range",data:[],fillColor:'#aaaaaa'},
      {label:"Points count range",data:[],fillColor:'#aaaacc'}
    ]
  }

  for(var i=0;i<stats['points_ranges'].length;i++) {
    var s = stats['points_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    occs_ranges.labels.push(s.label);
    occs_ranges.datasets[1].data.push(s.value);
  }
  for(var i=0;i<stats['occs_ranges'].length;i++) {
    var s = stats['occs_ranges'][i];
    occs_ranges.datasets[0].data.push(s.value);
  }
  new Chart(document.querySelector('.occs_ranges canvas').getContext('2d')).Bar(occs_ranges,{});

  var eoo_ranges={
    labels:[],
    datasets:[
      {label:"Extent of occurrence",data:[],fillColor:'#aaaaaa'}
    ]
  }
  for(var i=0;i<stats['eoo_ranges'].length;i++) {
    var s = stats['eoo_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    eoo_ranges.labels.push(s.label);
    eoo_ranges.datasets[0].data.push(s.value);
  }
  new Chart(document.querySelector('.eoo_ranges canvas').getContext('2d')).Bar(eoo_ranges,{});

  var aoo_ranges={
    labels:[],
    datasets:[
      {label:"Area of occupancy",data:[],fillColor:'#aaaaaa'}
    ]
  }
  for(var i=0;i<stats['aoo_ranges'].length;i++) {
    var s = stats['aoo_ranges'][i];
    s.label=(""+s.label).replace(/\.0/g,"").replace('-',' to ');
    if(i==0) {
      s.label='0';
    }
    aoo_ranges.labels.push(s.label);
    aoo_ranges.datasets[0].data.push(s.value);
  }
  console.log(aoo_ranges);
  new Chart(document.querySelector('.aoo_ranges canvas').getContext('2d')).Bar(aoo_ranges,{});
};

