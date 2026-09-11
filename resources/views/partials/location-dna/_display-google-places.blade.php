{{--
  Important Places on the Google DISPLAY map — one helper for both Google display tiers.

  Included by components/location-dna-map.blade.php before each of its Google scripts.
  `@once` keeps it to one definition per page however many map components render.

  The rule is the one the edit widget and the MapLibre renderer already follow:
  every located place gets a pin; a place measured in MILES also gets a ring at that
  distance; a historical travel-time (minutes) place gets a pin and NO ring — a circle
  would assert a reachable area that nothing here has computed. A place with no
  coordinate draws nothing, never a pin at 0,0.

  The pin title is a plain tooltip string (the place type), never HTML.
--}}
@once
<script>
  window.ldnaDisplayDrawGooglePlaces = function (gMap, places, bounds) {
    var extended = false;
    (places || []).forEach(function (p) {
      if (!p || p.lat === null || p.lng === null || p.lat === undefined || p.lng === undefined) return;
      var lat = Number(p.lat), lng = Number(p.lng);
      if (!isFinite(lat) || !isFinite(lng)) return;

      var marker = new google.maps.Marker({
        position: { lat: lat, lng: lng },
        map: gMap,
        title: String(p.type || 'Important place'),
        icon: {
          path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
          scale: 5, fillColor: '#dc2626', fillOpacity: 1,
          strokeColor: '#ffffff', strokeWeight: 1.5,
        },
        zIndex: 20,
      });
      bounds.extend(marker.getPosition());
      extended = true;

      var pref  = String(p.distance_pref || p.distance_preference || p.distpref || '').toLowerCase();
      var miles = Number(p.distance_value);
      if (pref === 'miles' && isFinite(miles) && miles > 0) {
        var ring = new google.maps.Circle({
          center: { lat: lat, lng: lng }, radius: miles * 1609.34,
          fillColor: '#dc2626', fillOpacity: 0.07,
          strokeColor: '#dc2626', strokeWeight: 1.5, strokeOpacity: 0.6,
          clickable: false, map: gMap,
        });
        bounds.union(ring.getBounds());
      }
    });
    return extended;
  };
</script>
@endonce
