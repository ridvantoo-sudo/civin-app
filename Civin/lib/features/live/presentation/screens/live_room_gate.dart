import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/live/data/models/live_room_model.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/pk/presentation/screens/live_room_with_pk_screen.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class LiveRoomGate extends ConsumerStatefulWidget {
  const LiveRoomGate({required this.roomId, this.initial, super.key});

  final String roomId;
  final LiveRoom? initial;

  @override
  ConsumerState<LiveRoomGate> createState() => _LiveRoomGateState();
}

final class _LiveRoomGateState extends ConsumerState<LiveRoomGate> {
  LiveRoom? _room;
  String? _error;

  @override
  void initState() {
    super.initState();
    _room = widget.initial;
    if (_room == null) {
      _load();
    }
  }

  Future<void> _load() async {
    try {
      final Response<dynamic> response = await ref
          .read(dioClientProvider)
          .get<dynamic>('/api/v1/live/${widget.roomId}');
      final Object? body = response.data;
      final Object? data = body is Map ? body['data'] : null;
      if (data is! Map) {
        throw const FormatException('Invalid live room response.');
      }
      if (!mounted) return;
      setState(() {
        _room = LiveRoomModel.fromJson(Map<String, dynamic>.from(data));
        _error = null;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final LiveRoom? room = _room;
    if (room != null) return LiveRoomWithPkScreen(room: room);
    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Live')),
        body: Center(child: Text(_error!)),
      );
    }
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
