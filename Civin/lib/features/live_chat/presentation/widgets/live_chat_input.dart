import 'package:emoji_picker_flutter/emoji_picker_flutter.dart';
import 'package:flutter/foundation.dart' as foundation;
import 'package:flutter/material.dart';

final class LiveChatInput extends StatefulWidget {
  const LiveChatInput({
    required this.enabled,
    required this.isSending,
    required this.onSend,
    super.key,
  });

  final bool enabled;
  final bool isSending;
  final Future<bool> Function(String message) onSend;

  @override
  State<LiveChatInput> createState() => _LiveChatInputState();
}

final class _LiveChatInputState extends State<LiveChatInput> {
  final TextEditingController _controller = TextEditingController();
  final FocusNode _focusNode = FocusNode();
  bool _showEmoji = false;

  @override
  void dispose() {
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final String text = _controller.text;
    final bool sent = await widget.onSend(text);
    if (sent && mounted) {
      _controller.clear();
      setState(() => _showEmoji = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            IconButton(
              tooltip: 'Emoji',
              onPressed: !widget.enabled
                  ? null
                  : () {
                      setState(() => _showEmoji = !_showEmoji);
                      if (_showEmoji) {
                        _focusNode.unfocus();
                      } else {
                        _focusNode.requestFocus();
                      }
                    },
              icon: Icon(
                _showEmoji
                    ? Icons.keyboard_rounded
                    : Icons.emoji_emotions_outlined,
                color: Colors.white,
              ),
            ),
            Expanded(
              child: TextField(
                controller: _controller,
                focusNode: _focusNode,
                enabled: widget.enabled && !widget.isSending,
                style: const TextStyle(color: Colors.white),
                maxLength: 500,
                maxLines: 3,
                minLines: 1,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _submit(),
                onTap: () {
                  if (_showEmoji) setState(() => _showEmoji = false);
                },
                decoration: InputDecoration(
                  counterText: '',
                  hintText: 'Say something…',
                  hintStyle: TextStyle(
                    color: Colors.white.withValues(alpha: 0.55),
                  ),
                  filled: true,
                  fillColor: Colors.black.withValues(alpha: 0.45),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(22),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              tooltip: 'Send',
              onPressed: widget.enabled && !widget.isSending ? _submit : null,
              style: IconButton.styleFrom(
                backgroundColor: theme.colorScheme.primary,
                foregroundColor: Colors.white,
              ),
              icon: widget.isSending
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_rounded),
            ),
          ],
        ),
        AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          child: _showEmoji
              ? SizedBox(
                  height: 260,
                  child: EmojiPicker(
                    textEditingController: _controller,
                    onBackspacePressed: () {},
                    config: Config(
                      height: 256,
                      checkPlatformCompatibility: true,
                      emojiViewConfig: EmojiViewConfig(
                        backgroundColor: const Color(0xFF15151C),
                        emojiSizeMax:
                            28 *
                            (foundation.defaultTargetPlatform ==
                                    TargetPlatform.iOS
                                ? 1.2
                                : 1.0),
                      ),
                      categoryViewConfig: const CategoryViewConfig(
                        backgroundColor: Color(0xFF101018),
                        indicatorColor: Color(0xFF6C4DFF),
                        iconColorSelected: Color(0xFF6C4DFF),
                      ),
                      bottomActionBarConfig: const BottomActionBarConfig(
                        enabled: false,
                      ),
                    ),
                  ),
                )
              : const SizedBox.shrink(),
        ),
      ],
    );
  }
}
