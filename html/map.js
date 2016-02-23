function map(occurrences) {
  var map = L.map('map').setView([0,0], 1);

  var land = L.tileLayer('http://{s}.tile3.opencyclemap.org/landscape/{z}/{x}/{y}.png')//.addTo(map);
  var ocm = L.tileLayer('http://{s}.tile.opencyclemap.org/cycle/{z}/{x}/{y}.png').addTo(map);
  var osm = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png')//.addTo(map);

  var base = { Landscape: land, OpenCycleMap: ocm, OpenStreetMap: osm };

  var markers = L.markerClusterGroup().addTo(map);

  for(var i =0;i<occurrences.length;i++) {
    var occ = occurrences[i];
    if(typeof occ.decimalLatitude == 'number' && typeof occ.decimalLongitude == 'number' 
       && occ.decimalLatitude != 0.0 && occ.decimalLongitude != 0.0) {
        try {
          var point = L.marker([occ.decimalLatitude,occ.decimalLongitude]);
          point.bindPopup(make_popup(occ));
          markers.addLayer(point);
        }catch(e) { }
      }
  }

  var layers = {
    'Occurrences': markers,
    'Extent of occurrence': L.geoJson(eoo_geojson).addTo(map),
    'Extent of occurrence - Historic': L.geoJson(eoo_historic_geojson).addTo(map),
    'Extent of occurrence - Recent': L.geoJson(eoo_recent_geojson).addTo(map),
    'Area of occupancy': L.geoJson(aoo_geojson).addTo(map),
    'Area of occupancy - Historic': L.geoJson(aoo_historic_geojson).addTo(map),
    'Area of occupancy - Recent': L.geoJson(aoo_recent_geojson).addTo(map),
    'Clusters': L.geoJson(clusters_geojson).addTo(map),
    'Clusters - Historic': L.geoJson(clusters_historic_geojson).addTo(map),
    'Clusters - Recent': L.geoJson(clusters_recent_geojson).addTo(map)
  };
                      
  L.control.layers(base,layers).addTo(map);
  L.control.scale().addTo(map);

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

