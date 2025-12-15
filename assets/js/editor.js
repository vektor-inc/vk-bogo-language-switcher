( function () {
	const switcherClassString = window.vkblsSwitcherClasses || '';

	if ( ! switcherClassString ) {
		return;
	}

	const addClasses = ( root ) => {
		if ( ! root ) {
			return;
		}
		const uls = root.querySelectorAll( 'ul.bogo-language-switcher' );
		uls.forEach( ( ul ) => {
			if ( ul.dataset.vkblsApplied ) {
				return;
			}
			ul.className = `${ ul.className } ${ switcherClassString }`.trim();
			ul.dataset.vkblsApplied = '1';
		} );
	};

	const observe = ( doc ) => {
		if ( ! doc || doc._vkblsObserved ) {
			return;
		}
		doc._vkblsObserved = true;
		addClasses( doc );
		const observer = new MutationObserver( () => addClasses( doc ) );
		observer.observe( doc.body, { childList: true, subtree: true } );
	};

	// Current document (post/page editor).
	window.addEventListener( 'DOMContentLoaded', () => {
		observe( document );

		// Site editor uses iframe.
		const maybeObserveFrames = () => {
			const frames = document.querySelectorAll( 'iframe[name="editor-canvas"], iframe.editor-canvas' );
			frames.forEach( ( frame ) => {
				try {
					observe( frame.contentDocument );
				} catch ( e ) {
					// ignore cross-origin issues
				}
			} );
		};

		maybeObserveFrames();
		const frameObserver = new MutationObserver( maybeObserveFrames );
		frameObserver.observe( document.body, { childList: true, subtree: true } );
	} );
} )();
