( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		const containers = document.querySelectorAll(
			'.venue-map-block[data-venues]'
		);

		containers.forEach( function ( container ) {
			const venuesJson = container.getAttribute( 'data-venues' );
			const zoom = parseInt(
				container.getAttribute( 'data-zoom' ) || '4',
				10
			);

			let venues;
			try {
				venues = JSON.parse( venuesJson );
			} catch ( e ) {
				return;
			}

			if ( ! venues || venues.length === 0 ) {
				return;
			}

			// Leaflet must be loaded before this script runs.
			if ( typeof L === 'undefined' ) {
				return;
			}

			const map = L.map( container ).setView(
				[ venues[ 0 ].lat, venues[ 0 ].lng ],
				zoom
			);

			L.tileLayer(
				'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				{
					attribution:
						'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
					maxZoom: 19,
				}
			).addTo( map );

			const bounds = [];

			venues.forEach( function ( venue ) {
				const latlng = [ venue.lat, venue.lng ];
				bounds.push( latlng );

				let popupContent = '<strong>' + venue.name + '</strong>';
				if ( venue.city ) {
					popupContent += '<br>' + venue.city;
				}
				if ( venue.count > 0 ) {
					popupContent +=
						'<br>' + venue.count + ' beer(s) checked in here';
				}
				if ( venue.url && typeof venue.url === 'string' ) {
					popupContent +=
						'<br><a href="' + venue.url + '">View beers</a>';
				}

				L.marker( latlng ).addTo( map ).bindPopup( popupContent );
			} );

			if ( bounds.length > 1 ) {
				map.fitBounds( bounds, { padding: [ 50, 50 ] } );
			}
		} );
	} );
} )();
