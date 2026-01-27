/**
 * Pint Load More — replaces pagination with a "Load More" button
 * that fetches the next page and appends beer cards to the grid.
 */
( function () {
	'use strict';

	const SELECTOR_GRID = '.pint-beer-cards';
	const SELECTOR_PAGINATION = '.wp-block-query-pagination';

	function init() {
		const grids = document.querySelectorAll( SELECTOR_GRID );

		grids.forEach( function ( grid ) {
			const query = grid.closest( '.wp-block-query' );
			if ( ! query ) {
				return;
			}

			const pagination = query.querySelector( SELECTOR_PAGINATION );
			if ( ! pagination ) {
				return;
			}

			const nextLink = pagination.querySelector( '.wp-block-query-pagination-next' );
			if ( ! nextLink || ! nextLink.href ) {
				return;
			}

			// Hide the default pagination.
			pagination.style.display = 'none';

			// Create load-more button.
			const wrapper = document.createElement( 'div' );
			wrapper.className = 'pint-load-more';
			wrapper.style.textAlign = 'center';
			wrapper.style.padding = '2rem 0';

			const button = document.createElement( 'button' );
			button.className = 'wp-element-button';
			button.textContent = 'Load More Beers';
			button.dataset.nextUrl = nextLink.href;

			wrapper.appendChild( button );
			query.appendChild( wrapper );

			button.addEventListener( 'click', function () {
				loadMore( button, grid );
			} );
		} );
	}

	function loadMore( button, grid ) {
		const url = button.dataset.nextUrl;
		if ( ! url ) {
			return;
		}

		button.disabled = true;
		button.textContent = 'Loading\u2026';

		fetch( url )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				const doc = new DOMParser().parseFromString( html, 'text/html' );
				const newGrid = doc.querySelector( SELECTOR_GRID );

				if ( newGrid ) {
					// Append each new card <li> to the existing grid.
					Array.from( newGrid.children ).forEach( function ( child ) {
						grid.appendChild( child.cloneNode( true ) );
					} );
				}

				// Check for a next page link in the fetched document.
				const newQuery = newGrid
					? newGrid.closest( '.wp-block-query' )
					: null;
				const newNext = newQuery
					? newQuery.querySelector( '.wp-block-query-pagination-next' )
					: doc.querySelector( '.wp-block-query-pagination-next' );

				if ( newNext && newNext.href ) {
					button.dataset.nextUrl = newNext.href;
					button.disabled = false;
					button.textContent = 'Load More Beers';
				} else {
					// No more pages — remove the button.
					button.parentElement.remove();
				}
			} )
			.catch( function () {
				button.disabled = false;
				button.textContent = 'Load More Beers';
			} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
