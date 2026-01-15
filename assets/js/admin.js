/**
 * Migrations Admin JavaScript
 *
 * Handles AJAX interactions with the REST API for migration actions.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 */

( function( domReady, apiFetch, $ ) {
	'use strict';

	/**
	 * Initialize the migrations admin functionality.
	 */
	function init() {
		// Initialize Select2 on tags multi-select.
		initSelect2();

		const container = document.querySelector( '.stellarwp-migrations-list' );
		if ( ! container ) {
			return;
		}

		const restUrl = container.dataset.restUrl;

		if ( ! restUrl ) {
			console.error( 'Migrations: Missing REST URL' );
			return;
		}

		// Attach event listeners to all action buttons
		container.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '.stellarwp-migration-btn[data-action]' );
			if ( ! button ) {
				return;
			}

			event.preventDefault();

			const card = button.closest( '.stellarwp-migration-card' );
			if ( ! card ) {
				return;
			}

			const migrationId = card.dataset.migrationId;
			const action = button.dataset.action;

			if ( ! migrationId || ! action ) {
				return;
			}

			handleAction( card, button, migrationId, action, restUrl );
		} );
	}

	/**
	 * Handle a migration action (run or rollback).
	 *
	 * @param {HTMLElement} card        The migration card element.
	 * @param {HTMLElement} button      The button that was clicked.
	 * @param {string}      migrationId The migration ID.
	 * @param {string}      action      The action to perform ('run' or 'rollback').
	 * @param {string}      restUrl     The REST API base URL.
	 */
	function handleAction( card, button, migrationId, action, restUrl ) {
		// Disable all buttons on this card
		const buttons = card.querySelectorAll( '.stellarwp-migration-btn' );
		buttons.forEach( function( btn ) {
			btn.disabled = true;
		} );

		// Add loading state to clicked button
		button.classList.add( 'stellarwp-migration-btn--loading' );
		const originalText = button.textContent;
		button.textContent = action === 'run' ? 'Running...' : 'Rolling back...';

		// Build the endpoint path (relative to REST root)
		const endpoint = restUrl + '/migrations/' + encodeURIComponent( migrationId ) + '/' + action;

		// Make the API request using wp.apiFetch
		apiFetch( {
			url: endpoint,
			method: 'POST',
		} )
			.then( function( data ) {
				if ( data.success ) {
					showMessage( card, 'success', getSuccessMessage( action, data ) );
					updateCardStatus( card, action, data );
				} else {
					const errorMessage = data.message || 'An error occurred';
					showMessage( card, 'error', errorMessage );
				}
			} )
			.catch( function( error ) {
				console.error( 'Migrations API error:', error );
				const errorMessage = error.message || 'Network error. Please try again.';
				showMessage( card, 'error', errorMessage );
			} )
			.finally( function() {
				// Re-enable buttons and remove loading state
				buttons.forEach( function( btn ) {
					btn.disabled = false;
				} );
				button.classList.remove( 'stellarwp-migration-btn--loading' );
				button.textContent = originalText;
			} );
	}

	/**
	 * Get a success message for the action.
	 *
	 * @param {string} action The action that was performed.
	 * @param {Object} data   The response data.
	 *
	 * @return {string} The success message.
	 */
	function getSuccessMessage( action, data ) {
		if ( action === 'run' ) {
			return 'Migration scheduled. Execution ID: ' + ( data.execution_id || 'N/A' );
		}
		return 'Rollback scheduled. Execution ID: ' + ( data.execution_id || 'N/A' );
	}

	/**
	 * Show a message in the card.
	 *
	 * @param {HTMLElement} card    The migration card element.
	 * @param {string}      type    Message type ('success' or 'error').
	 * @param {string}      message The message to display.
	 */
	function showMessage( card, type, message ) {
		const messageEl = card.querySelector( '.stellarwp-migration-card__message' );
		if ( ! messageEl ) {
			return;
		}

		messageEl.className = 'stellarwp-migration-card__message stellarwp-migration-card__message--' + type;
		messageEl.textContent = message;
		messageEl.style.display = 'block';

		// Hide after 5 seconds
		setTimeout( function() {
			messageEl.style.display = 'none';
		}, 5000 );
	}

	/**
	 * Update the card status after a successful action.
	 *
	 * @param {HTMLElement} card   The migration card element.
	 * @param {string}      action The action that was performed.
	 * @param {Object}      data   The response data.
	 */
	function updateCardStatus( card, action, data ) {
		const statusLabel = card.querySelector( '.stellarwp-migration-card__status-label' );
		if ( statusLabel ) {
			// Update status to "scheduled"
			statusLabel.className = 'stellarwp-migration-card__status-label stellarwp-migration-card__status-label--scheduled';
			statusLabel.textContent = 'Scheduled';
		}

		// Update progress bar status
		const progressFill = card.querySelector( '.stellarwp-migration-progress__fill' );
		if ( progressFill ) {
			progressFill.className = 'stellarwp-migration-progress__fill stellarwp-migration-progress__fill--scheduled';
		}

		// Update button visibility
		updateButtonVisibility( card, 'scheduled' );
	}

	/**
	 * Update button visibility based on the new status.
	 *
	 * @param {HTMLElement} card   The migration card element.
	 * @param {string}      status The new status.
	 */
	function updateButtonVisibility( card, status ) {
		const actionsContainer = card.querySelector( '.stellarwp-migration-card__actions' );
		if ( ! actionsContainer ) {
			return;
		}

		// For scheduled status, hide all action buttons temporarily
		// The page will need to refresh to get accurate button states
		if ( status === 'scheduled' ) {
			const buttons = actionsContainer.querySelectorAll( '.stellarwp-migration-btn' );
			buttons.forEach( function( btn ) {
				btn.style.display = 'none';
			} );
		}
	}

	/**
	 * Initialize Select2 on tags multi-select.
	 */
	function initSelect2() {
		if ( typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined' ) {
			return;
		}

		$( '.stellarwp-migrations-select2' ).select2( {
			allowClear: true,
			width: '100%',
		} );
	}

	// Initialize when DOM is ready using wp.domReady.
	domReady( init );
} )( wp.domReady, wp.apiFetch, jQuery );
