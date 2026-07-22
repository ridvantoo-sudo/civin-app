import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/live/data/datasources/live_remote_data_source.dart';
import 'package:civin/features/live/data/repositories/live_repository_impl.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeLiveRemoteDataSource remote;
  late LiveRepositoryImpl repository;

  setUp(() {
    remote = _FakeLiveRemoteDataSource();
    repository = LiveRepositoryImpl(remote);
  });

  test('returns currently live rooms', () async {
    final RepositoryResult<List<LiveRoom>> result = await repository
        .getLiveRooms();

    expect(result, isA<RepositorySuccess<List<LiveRoom>>>());
    final List<LiveRoom> rooms =
        (result as RepositorySuccess<List<LiveRoom>>).data;
    expect(rooms.single.title, 'Morning update');
  });

  test('forwards room creation and lifecycle operations', () async {
    final RepositoryResult<LiveRoom> created = await repository.createLiveRoom(
      title: 'Town hall',
      categoryId: 3,
    );
    final RepositoryResult<LiveConnection> started = await repository
        .startStream('room-1');
    await repository.endStream('room-1');

    expect(created, isA<RepositorySuccess<LiveRoom>>());
    expect(started, isA<RepositorySuccess<LiveConnection>>());
    expect(remote.createdTitle, 'Town hall');
    expect(remote.createdCategoryId, 3);
    expect(remote.startedRoomId, 'room-1');
    expect(remote.endedRoomId, 'room-1');
    expect(
      (started as RepositorySuccess<LiveConnection>).data.rtc.channel,
      'channel-1',
    );
  });

  test('maps data source exceptions to repository failures', () async {
    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/live'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/live'),
        statusCode: 503,
        data: <String, dynamic>{'message': 'Unavailable'},
      ),
    );

    final RepositoryResult<List<LiveRoom>> result = await repository
        .getLiveRooms();

    expect(result, isA<RepositoryFailure<List<LiveRoom>>>());
    final AppFailure failure =
        (result as RepositoryFailure<List<LiveRoom>>).failure;
    expect(failure, isA<NetworkFailure>());
    expect(failure.message, 'Unavailable');
  });
}

final class _FakeLiveRemoteDataSource implements LiveRemoteDataSource {
  Object? error;
  String? createdTitle;
  int? createdCategoryId;
  String? startedRoomId;
  String? endedRoomId;

  static const LiveRoom room = LiveRoom(
    id: 'room-1',
    title: 'Morning update',
    channelName: 'channel-1',
    hostName: 'River',
    viewerCount: 42,
    isLive: true,
  );

  static const LiveConnection connection = LiveConnection(
    room: room,
    rtc: LiveRtcCredentials(
      appId: 'app-id',
      channel: 'channel-1',
      uid: 11,
      token: 'token',
    ),
  );

  void _throwIfNeeded() {
    final Object? value = error;
    if (value is DioException) throw value;
    if (value is Exception) throw value;
  }

  @override
  Future<List<LiveRoom>> getLiveRooms() async {
    _throwIfNeeded();
    return const <LiveRoom>[room];
  }

  @override
  Future<List<LiveCategory>> getCategories() async {
    _throwIfNeeded();
    return const <LiveCategory>[LiveCategory(id: 1, name: 'Talk')];
  }

  @override
  Future<LiveRoom> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  }) async {
    createdTitle = title;
    createdCategoryId = categoryId;
    return room;
  }

  @override
  Future<LiveConnection> startStream(String roomId) async {
    startedRoomId = roomId;
    return connection;
  }

  @override
  Future<LiveConnection> joinRoom(String roomId) async => connection;

  @override
  Future<void> leaveRoom(String roomId) async {}

  @override
  Future<void> endStream(String roomId) async {
    endedRoomId = roomId;
  }
}
