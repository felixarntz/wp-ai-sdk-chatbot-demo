/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback, useRef } from "@wordpress/element";

interface Size {
  width: number;
  height: number;
}

interface Position {
  bottom: number;
  right: number;
}

interface ResizableState {
  size: Size;
  position: Position;
}

const DEFAULT_SIZE = { width: 400, height: 500 };
const DEFAULT_POSITION = { bottom: 32, right: 32 }; // 2rem = 32px
const MIN_SIZE = { width: 320, height: 400 };
const EDGE_MARGIN = 20; // 20px from body edges

/**
 * Custom hook for managing resizable chatbot UI with localStorage persistence
 */
export function useResizable(currentUserId: number) {
  const [state, setState] = useState<ResizableState>({
    size: DEFAULT_SIZE,
    position: DEFAULT_POSITION,
  });

  const [isResizing, setIsResizing] = useState(false);
  const [resizeHandle, setResizeHandle] = useState<string | null>(null);
  const dragStartRef = useRef<{
    x: number;
    y: number;
    initialSize: Size;
    initialPosition: Position;
  } | null>(null);

  // Load saved size and position from localStorage
  useEffect(() => {
    const loadSavedState = () => {
      try {
        const savedSizeStr = localStorage.getItem(
          `chatbot_size_user_${currentUserId}`,
        );
        const savedPositionStr = localStorage.getItem(
          `chatbot_position_user_${currentUserId}`,
        );

        if (savedSizeStr || savedPositionStr) {
          const savedSize = savedSizeStr ? JSON.parse(savedSizeStr) : null;
          const savedPosition = savedPositionStr
            ? JSON.parse(savedPositionStr)
            : null;

          setState({
            size: {
              width: Math.max(
                savedSize?.width || DEFAULT_SIZE.width,
                MIN_SIZE.width,
              ),
              height: Math.max(
                savedSize?.height || DEFAULT_SIZE.height,
                MIN_SIZE.height,
              ),
            },
            position: {
              bottom: Math.max(
                savedPosition?.bottom || DEFAULT_POSITION.bottom,
                EDGE_MARGIN,
              ),
              right: Math.max(
                savedPosition?.right || DEFAULT_POSITION.right,
                EDGE_MARGIN,
              ),
            },
          });
        }
      } catch (error) {
        // Fall back to defaults if loading fails
        console.warn("Failed to load chatbot size from localStorage:", error);
      }
    };

    if (currentUserId) {
      loadSavedState();
    }
  }, [currentUserId]);

  // Save size and position to localStorage
  const saveState = useCallback(
    (newState: ResizableState) => {
      try {
        localStorage.setItem(
          `chatbot_size_user_${currentUserId}`,
          JSON.stringify(newState.size),
        );
        localStorage.setItem(
          `chatbot_position_user_${currentUserId}`,
          JSON.stringify(newState.position),
        );
      } catch (error) {
        console.warn("Failed to save chatbot size to localStorage:", error);
      }
    },
    [currentUserId],
  );

  // Debounced save function to avoid too many writes
  const debouncedSave = useCallback(
    (() => {
      let timeoutId: number;
      return (newState: ResizableState) => {
        clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => saveState(newState), 300);
      };
    })(),
    [saveState],
  );

  // Get constrained size within viewport bounds
  const getConstrainedState = useCallback(
    (size: Size, position: Position): ResizableState => {
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;

      // Ensure minimum size
      const constrainedWidth = Math.max(size.width, MIN_SIZE.width);
      const constrainedHeight = Math.max(size.height, MIN_SIZE.height);

      // Ensure it fits within viewport with margins
      const maxWidth = viewportWidth - EDGE_MARGIN * 2;
      const maxHeight = viewportHeight - EDGE_MARGIN * 2;

      const finalWidth = Math.min(constrainedWidth, maxWidth);
      const finalHeight = Math.min(constrainedHeight, maxHeight);

      // Constrain position to keep chatbot within bounds
      const maxRight = viewportWidth - finalWidth - EDGE_MARGIN;
      const maxBottom = viewportHeight - finalHeight - EDGE_MARGIN;

      const constrainedPosition = {
        right: Math.max(EDGE_MARGIN, Math.min(position.right, maxRight)),
        bottom: Math.max(EDGE_MARGIN, Math.min(position.bottom, maxBottom)),
      };

      return {
        size: { width: finalWidth, height: finalHeight },
        position: constrainedPosition,
      };
    },
    [],
  );

  // Handle resize start
  const handleResizeStart = useCallback(
    (handle: string, event: React.MouseEvent) => {
      event.preventDefault();
      setIsResizing(true);
      setResizeHandle(handle);

      dragStartRef.current = {
        x: event.clientX,
        y: event.clientY,
        initialSize: state.size,
        initialPosition: state.position,
      };

      // Prevent text selection during resize
      document.body.style.userSelect = "none";
    },
    [state],
  );

  // Handle resize move
  const handleResizeMove = useCallback(
    (event: MouseEvent) => {
      if (!isResizing || !resizeHandle || !dragStartRef.current) return;

      const {
        x: startX,
        y: startY,
        initialSize,
        initialPosition,
      } = dragStartRef.current;
      const deltaX = event.clientX - startX;
      const deltaY = event.clientY - startY;

      let newSize = { ...initialSize };
      let newPosition = { ...initialPosition };

      switch (resizeHandle) {
        case "right":
          newSize.width = initialSize.width + deltaX;
          break;
        case "bottom":
          newSize.height = initialSize.height - deltaY; // Subtract because we're positioned from bottom
          break;
        case "bottom-right":
          newSize.width = initialSize.width + deltaX;
          newSize.height = initialSize.height - deltaY;
          break;
        case "left":
          newSize.width = initialSize.width - deltaX;
          newPosition.right = initialPosition.right - deltaX;
          break;
        case "top":
          newSize.height = initialSize.height + deltaY;
          break;
        case "top-left":
          newSize.width = initialSize.width - deltaX;
          newSize.height = initialSize.height + deltaY;
          newPosition.right = initialPosition.right - deltaX;
          break;
        case "top-right":
          newSize.width = initialSize.width + deltaX;
          newSize.height = initialSize.height + deltaY;
          break;
        case "bottom-left":
          newSize.width = initialSize.width - deltaX;
          newSize.height = initialSize.height - deltaY;
          newPosition.right = initialPosition.right - deltaX;
          break;
      }

      const constrainedState = getConstrainedState(newSize, newPosition);
      setState(constrainedState);
    },
    [isResizing, resizeHandle, getConstrainedState],
  );

  // Handle resize end
  const handleResizeEnd = useCallback(() => {
    if (isResizing) {
      setIsResizing(false);
      setResizeHandle(null);
      dragStartRef.current = null;
      document.body.style.userSelect = "";

      // Save the final state
      debouncedSave(state);
    }
  }, [isResizing, state, debouncedSave]);

  // Attach global mouse events
  useEffect(() => {
    if (isResizing) {
      document.addEventListener("mousemove", handleResizeMove);
      document.addEventListener("mouseup", handleResizeEnd);
    }

    return () => {
      document.removeEventListener("mousemove", handleResizeMove);
      document.removeEventListener("mouseup", handleResizeEnd);
    };
  }, [isResizing, handleResizeMove, handleResizeEnd]);

  // Reset to default size and position
  const resetSize = useCallback(() => {
    const defaultState = {
      size: DEFAULT_SIZE,
      position: DEFAULT_POSITION,
    };
    setState(defaultState);
    saveState(defaultState);
  }, [saveState]);

  return {
    size: state.size,
    position: state.position,
    isResizing,
    resizeHandle,
    handleResizeStart,
    resetSize,
  };
}
