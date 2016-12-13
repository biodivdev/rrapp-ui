function map() {
  var div = document.createElement('div');
  div.classList.add('pure-u-1');
  div.classList.add('map');
  div.setAttribute('id','map');
  document.querySelector('.occurrences').parentNode.insertBefore(div,document.querySelector(".occurrences"));

  var map = L.map('map').setView([0,0], 1);

  var land = L.tileLayer('http://{s}.tile3.opencyclemap.org/landscape/{z}/{x}/{y}.png')//.addTo(map);
  var ocm = L.tileLayer('http://{s}.tile.opencyclemap.org/cycle/{z}/{x}/{y}.png').addTo(map);
  var osm = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png')//.addTo(map);

  var base = { Landscape: land, OpenCycleMap: ocm, OpenStreetMap: osm };
  var markers = L.markerClusterGroup().addTo(map);

  var occs = document.querySelectorAll(".occ");
  for(var i =0;i<occs.length;i++) {
    var occ = {};
    var lines = occs[i].querySelectorAll("tr");
    for(var o=0;o<lines.length;o++) {
      var key= lines[o].querySelector("th").innerText;
      var val= lines[o].querySelector("td").innerText;
      if(key == 'decimalLatitude' || key == 'decimalLongitude' ) {
        try {
          occ[key] = parseFloat(val);
        }catch(e){ }
      } else {
        occ[key] = val;
      }
    }
    if(typeof occ.decimalLatitude == 'number' && typeof occ.decimalLongitude == 'number' 
       && occ.decimalLatitude != 0.0 && occ.decimalLongitude != 0.0) {
        try {
          var point = L.marker([occ.decimalLatitude,occ.decimalLongitude]);
          point.bindPopup(make_popup(occ));
          markers.addLayer(point);
        }catch(e) { }
      }
  }

  L.control.scale().addTo(map);

  fetch(location.href.replace("/taxon","/api/taxon")+"/analysis")
  .then(function(r) {return r.text()})
  .then(function(r) {add_data(JSON.parse( r ))})
  .catch(function(e) {console.log(e)})

  function add_data(data) {
    var layers = {
      'Occurrences': markers,
      'Extent of occurrence': L.geoJson(data.eoo.all.geo).addTo(map),
      'Extent of occurrence - Historic': L.geoJson(data.eoo.historic.geo).addTo(map),
      'Extent of occurrence - Recent': L.geoJson(data.eoo.recent.geo).addTo(map),
      'Area of occupancy': L.geoJson(data.aoo.all.geo).addTo(map),
      'Area of occupancy - Historic': L.geoJson(data.aoo.historic.geo).addTo(map),
      'Area of occupancy - Recent': L.geoJson(data.aoo.recent.geo).addTo(map),
      'Area of occupancy variable': L.geoJson(data[ 'aoo-variadic' ].all.geo).addTo(map),
      'Area of occupancy variable - Historic': L.geoJson(data[ 'aoo-variadic' ].historic.geo).addTo(map),
      'Area of occupancy variable - Recent': L.geoJson(data[ 'aoo-variadic' ].recent.geo).addTo(map),
      'Location/Subpop.': L.geoJson(data.clusters.all.geo).addTo(map),
      'Location/Subpop. - Historic': L.geoJson(data.clusters.historic.geo).addTo(map),
      'Location/Subpop. - Recent': L.geoJson(data.clusters.recent.geo).addTo(map)
    };
                        
    L.control.layers(base,layers).addTo(map);
  }

}

function make_popup(props){
  var table = document.createElement('table');
  table.setAttribute('class','pure-table pure-table-striped');

  for(var k in props) {
    if(typeof props[k] == 'object') {
      for(var kk in props[k]) {
        if(kk.indexOf("-") >= 1){
        }else if(typeof props[k][kk] == 'string' || typeof props[k][kk] == 'number') {
          var tr = document.createElement('tr');
          var td = document.createElement('td');
          td.innerHTML = k+'<br />'+kk;
          tr.appendChild(td);
          var td2 = document.createElement('td');
          td2.innerHTML = ""+props[k][kk];
          tr.appendChild(td2);
          table.appendChild(tr);
        }
      }
    } else if(typeof props[k] == 'string' || typeof props[k] == 'number') {
      var tr = document.createElement('tr');
      var td = document.createElement('td');
      td.innerHTML = k;
      tr.appendChild(td);
      var td2 = document.createElement('td');
      td2.innerHTML = ""+props[k];
      tr.appendChild(td2);
      table.appendChild(tr);
    } else {
      continue;
    }
  }

  return table;
};


window.onload=map;
