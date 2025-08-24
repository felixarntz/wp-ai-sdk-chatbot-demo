/**
 * External dependencies
 */
import Markdown from 'markdown-to-jsx';

/**
 * WordPress dependencies
 */
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { MessagePart, MessagePartChannel } from '../../types';
import './style.scss';

export type MediaProps = {
	mimeType: string;
	src: string;
};

export type JsonTextareaProps = {
	data: unknown;
	label: string;
};

export type MessagePartsProps = {
	parts: MessagePart[];
};

/**
 * Renders a single media element.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
function Media( props: MediaProps ) {
	const { mimeType, src } = props;

	if ( mimeType.startsWith( 'image' ) ) {
		return <img src={ src } alt="" />;
	}

	if ( mimeType.startsWith( 'audio' ) ) {
		return <audio src={ src } controls />;
	}

	if ( mimeType.startsWith( 'video' ) ) {
		return <video src={ src } controls />;
	}

	return (
		<strong>
			{ __( 'File preview unavailable:', 'wp-ai-sdk-chatbot-demo' ) +
				' ' +
				src }
		</strong>
	);
}

/**
 * Renders a textarea with JSON formatted data.
 *
 * @since 0.5.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
function JsonTextarea( props: JsonTextareaProps ) {
	const { data, label } = props;

	const dataJson = useMemo( () => {
		return JSON.stringify( data, null, 2 );
	}, [ data ] );

	return (
		<textarea
			className="code"
			aria-label={ label }
			value={ dataJson }
			rows={ 5 }
			readOnly
		/>
	);
}

/**
 * Renders formatted message parts.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
export default function MessageParts( props: MessagePartsProps ) {
	const { parts } = props;

	return (
		<div className="wp-ai-sdk-chatbot-demo__message-parts">
			{ parts.map( ( part, index ) => {
				// Do not render model thinking / reasoning parts.
				if ( part.channel === MessagePartChannel.Thought ) {
					return null;
				}

				if ( 'text' in part ) {
					return (
						<div
							className="wp-ai-sdk-chatbot-demo__message-part"
							key={ index }
						>
							<Markdown
								options={ {
									forceBlock: true,
									forceWrapper: true,
								} }
							>
								{ part.text }
							</Markdown>
						</div>
					);
				}

				if ( 'file' in part ) {
					const { mimeType } = part.file;
					let url: string = '';
					if ( 'base64Data' in part.file ) {
						const { base64Data } = part.file;
						url = `data:${ mimeType };base64,${ base64Data }`;
					} else {
						url = part.file.url;
					}
					return (
						<div
							className="wp-ai-sdk-chatbot-demo__message-part"
							key={ index }
						>
							<Media mimeType={ mimeType } src={ url } />
						</div>
					);
				}

				if ( 'functionCall' in part ) {
					return (
						<div
							className="wp-ai-sdk-chatbot-demo__message-part"
							key={ index }
						>
							<JsonTextarea
								data={ part.functionCall }
								label={ __(
									'Function call data',
									'wp-ai-sdk-chatbot-demo'
								) }
							/>
						</div>
					);
				}

				if ( 'functionResponse' in part ) {
					return (
						<div
							className="wp-ai-sdk-chatbot-demo__message-part"
							key={ index }
						>
							<JsonTextarea
								data={ part.functionResponse }
								label={ __(
									'Function response data',
									'wp-ai-sdk-chatbot-demo'
								) }
							/>
						</div>
					);
				}

				return null;
			} ) }
		</div>
	);
}
