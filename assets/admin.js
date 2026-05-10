/**
 * Oblique AI Scout — Admin Dashboard JavaScript
 *
 * Handles clipboard copy, toast notifications, and select-all checkboxes.
 *
 * @package Oblique_AI_Scout
 */

( function ( $ ) {
	'use strict';

	var ObliqueScout = {
		/**
		 * Initialize on DOM ready.
		 */
		init: function () {
			this.bindSelectAll();
			this.bindCopyButton();
		},

		/**
		 * Select-all checkbox handler for the log table.
		 */
		bindSelectAll: function () {
			$( '#oblique-select-all' ).on( 'change', function () {
				$( '.oblique-cb' ).prop( 'checked', this.checked );
			} );
		},

		/**
		 * Copy-to-clipboard for bot patterns.
		 */
		bindCopyButton: function () {
			$( '#oblique-copy-btn' ).on( 'click', function ( e ) {
				e.preventDefault();

				var el   = document.getElementById( 'oblique-ua-patterns' );
				var btn  = this;
				var orig = btn.innerHTML;

				if ( ! el ) {
					return;
				}

				var text = el.innerText;

				var onSuccess = function () {
					ObliqueScout.showToast();
					btn.innerHTML = '✅ ' + ( obliqueScout.i18n.copied || 'Copied!' );
					setTimeout( function () {
						btn.innerHTML = orig;
					}, 2000 );
				};

				// Modern clipboard API.
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( onSuccess, function () {
						ObliqueScout.fallbackCopy( text, onSuccess );
					} );
				} else {
					ObliqueScout.fallbackCopy( text, onSuccess );
				}
			} );
		},

		/**
		 * Fallback copy using a temporary textarea (for HTTP or older browsers).
		 *
		 * @param {string}   text     Text to copy.
		 * @param {Function} callback Called on success.
		 */
		fallbackCopy: function ( text, callback ) {
			var textarea = document.createElement( 'textarea' );
			textarea.value          = text;
			textarea.style.position = 'fixed';
			textarea.style.top      = '-9999px';
			textarea.style.left     = '-9999px';
			document.body.appendChild( textarea );
			textarea.focus();
			textarea.select();

			try {
				var ok = document.execCommand( 'copy' );
				if ( ok && callback ) {
					callback();
				}
			} catch ( err ) {
				/* eslint-disable-next-line no-alert */
				alert( obliqueScout.i18n.copyFail || 'Please manually copy the patterns.' );
			}

			document.body.removeChild( textarea );
		},

		/**
		 * Show a centered toast notification.
		 */
		showToast: function () {
			var $toast = $( '#oblique-toast' );
			if ( ! $toast.length ) {
				return;
			}

			$toast.removeClass( 'oblique-toast--visible' );

			// Force reflow so animation restarts.
			void $toast[0].offsetWidth;

			$toast.addClass( 'oblique-toast--visible' );

			setTimeout( function () {
				$toast.removeClass( 'oblique-toast--visible' );
			}, 2200 );
		}
	};

	$( document ).ready( function () {
		ObliqueScout.init();
	} );
} )( jQuery );
