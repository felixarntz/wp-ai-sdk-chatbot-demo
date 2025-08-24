/**
 * Renders the loader.
 *
 * @since 0.1.0
 *
 * @returns The component to be rendered.
 */
export default function Loader() {
	return (
		<div className="wp-ai-sdk-chatbot-demo__loader-container">
			<svg
				width="50px"
				height="21px"
				viewBox="0 0 132 58"
				version="1.1"
				xmlns="http://www.w3.org/2000/svg"
			>
				<g stroke="none" fill="none">
					<g className="wp-ai-sdk-chatbot-demo__loader">
						<circle
							className="wp-ai-sdk-chatbot-demo__loader-dot"
							cx="25"
							cy="30"
							r="13"
						></circle>
						<circle
							className="wp-ai-sdk-chatbot-demo__loader-dot"
							cx="65"
							cy="30"
							r="13"
						></circle>
						<circle
							className="wp-ai-sdk-chatbot-demo__loader-dot"
							cx="105"
							cy="30"
							r="13"
						></circle>
					</g>
				</g>
			</svg>
		</div>
	);
}
