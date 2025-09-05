/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import { useResizable } from '../../hooks/use-resizable';
import './style.scss';

interface ResizableContainerProps {
	children: React.ReactNode;
	currentUserId: number;
	className?: string;
}

const RESIZE_HANDLES = [
	{ name: 'top', cursor: 'n-resize' },
	{ name: 'right', cursor: 'e-resize' },
	{ name: 'bottom', cursor: 's-resize' },
	{ name: 'left', cursor: 'w-resize' },
	{ name: 'top-left', cursor: 'nw-resize' },
	{ name: 'top-right', cursor: 'ne-resize' },
	{ name: 'bottom-left', cursor: 'sw-resize' },
	{ name: 'bottom-right', cursor: 'se-resize' },
];

/**
 * ResizableContainer component that provides drag handles for resizing
 */
export default function ResizableContainer( props: ResizableContainerProps ) {
	const { children, currentUserId, className } = props;

	const {
		size,
		position,
		isResizing,
		resizeHandle,
		handleResizeStart,
		resetSize,
	} = useResizable( currentUserId );

	const [ showResetButton, setShowResetButton ] = useState( false );

	const containerStyle: React.CSSProperties = {
		width: `${size.width}px`,
		height: `${size.height}px`,
		bottom: `${position.bottom}px`,
		right: `${position.right}px`,
		cursor: isResizing ? 'grabbing' : 'default',
	};

	return (
		<div
			className={ clsx(
				'wp-ai-sdk-resizable-container',
				{
					'is-resizing': isResizing,
					'show-reset': showResetButton,
				},
				className
			) }
			style={ containerStyle }
			onMouseEnter={ () => setShowResetButton( true ) }
			onMouseLeave={ () => !isResizing && setShowResetButton( false ) }
		>
			{/* Resize handles */}
			{ RESIZE_HANDLES.map( ( handle ) => (
				<div
					key={ handle.name }
					className={ clsx(
						'wp-ai-sdk-resize-handle',
						`wp-ai-sdk-resize-handle--${handle.name}`,
						{
							'is-active': resizeHandle === handle.name,
						}
					) }
					style={ { cursor: handle.cursor } }
					onMouseDown={ ( event ) => handleResizeStart( handle.name, event ) }
				/>
			) ) }

			{/* Reset button */}
			{ showResetButton && (
				<button
					className="wp-ai-sdk-reset-size-button"
					onClick={ resetSize }
					title="Reset chatbot size"
					type="button"
				>
					↺
				</button>
			) }

			{/* Container content */}
			<div className="wp-ai-sdk-resizable-content">
				{ children }
			</div>
		</div>
	);
}
