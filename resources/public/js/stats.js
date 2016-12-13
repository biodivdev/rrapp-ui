
function cats() {

  var canvas = document.createElement("canvas");
  canvas.classList.add("pure-u-1");
  canvas.classList.add("pure-u-lg-1-2");
  document.querySelector(".cats").appendChild(canvas);

  var categories=[];
  var labels=[];
  var colors=[];

  var cats = document.querySelectorAll(".cats .cat");
  for(var i=0;i<cats.length;i++) {
    var s=cats[i].dataset;
    var color="";
    if(s.key=='EX') {
      color='#000000';
    }else if(s.key=='EW') {
      color='#330000';
    }else if(s.key=='CR') {
      color='#663333';
    }else if(s.key=='EN') {
      color='#aa6666';
    }else if(s.key=='VU') {
      color='#ccaaaa';
    } else if(s.key=='NT') {
      color='#cccc66';
    } else if(s.key=='LC') {
      color='#aaaacc';
    } else if(s.key=='DD') {
      color='#aaaaaa';
    }
    colors.push(color);
    labels.push(s.key);
    categories.push(parseInt( s.val ));
  }

  var data={ datasets: [ { data: categories,backgroundColor:colors } ],labels:labels};

  var chart=new Chart(canvas.getContext('2d'),{options:{animationEasing:'linear',animationSteps:30},type:"pie",data:data});
  canvas.setAttribute("style","");
}

function occs() {
  var canvas = document.createElement("canvas");
  canvas.classList.add("pure-u-1");
  canvas.classList.add("pure-u-lg-1-2");
  document.querySelector(".occs").appendChild(canvas);

  var categories=[];
  var labels=[];
  var colors=[];

  var cats = document.querySelectorAll(".occs tr");
  for(var i=1;i<cats.length;i++) {
    if(i==1){
    var color="#ccc";
    } else {
    var color="#aac";
    }
    colors.push(color);
    labels.push(cats[i].querySelector("th").innerText);
    categories.push(parseInt(cats[i].querySelector("td").innerText ));
  }

  var data={ datasets: [ { data: categories,backgroundColor:colors } ],labels:labels};

  var chart=new Chart(canvas.getContext('2d'),{options:{animationEasing:'linear',animationSteps:30},type:"pie",data:data});
  canvas.setAttribute("style","");
}

function bar(bar) {
  var canvas = document.createElement("canvas");
  canvas.classList.add("pure-u-1");
  canvas.classList.add("pure-u-lg-1-2");
  bar.appendChild(canvas);

  var ranges=[];
  var labels=[];
  var colors=[];

  var cats = bar.querySelectorAll("tr");
  for(var i=0;i<cats.length;i++) {
    var s=cats[i].dataset;
    labels.push(s.from+" ~ "+s.to);
    ranges.push(parseInt( s.val ));
    colors.push("#ccc");
  }

  var data={ datasets: [ { data: ranges,backgroundColors:colors,label:bar.querySelector("h3").innerText} ],labels:labels};

  var chart=new Chart(canvas.getContext('2d'),{type:"bar",data:data});
  canvas.setAttribute("style","");
}

function bars() {
  var bars = document.querySelectorAll(".bar");
  for(var i=0;i<bars.length;i++) {
    bar(bars[i]);
  }
}

function stats() {
  cats();
  occs();
  bars();
}

window.onload=stats;
