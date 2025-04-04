const io = require('socket.io')(3000, {
    cors: {
        origin: "*",
    }
});

let rooms = {}; // Tracks room states

io.on('connection', (socket) => {
    console.log('User connected:', socket.id);

    // Join room
    socket.on('joinRoom', ({ roomId, username }) => {
        socket.join(roomId);
        console.log(`${username} joined room ${roomId}`);

        if (!rooms[roomId]) {
            rooms[roomId] = {
                users: {},
                currentQuestionIndex: 0,
                totalUsers: 0,
                answeredUsers: new Set(),
                answers: {}, // Track answers for fetchAnswers
            };
        }

        rooms[roomId].users[socket.id] = { username, answered: false };
        rooms[roomId].totalUsers = Object.keys(rooms[roomId].users).length;

        io.to(roomId).emit('roomUpdate', {
            answeredCount: rooms[roomId].answeredUsers.size,
            totalUsers: rooms[roomId].totalUsers,
            currentQuestionIndex: rooms[roomId].currentQuestionIndex,
        });
    });

    // Handle chat messages
    socket.on('chatMessage', ({ roomId, username, message }) => {
        io.to(roomId).emit('message', { username, message });
    });

    // Submit answer
    socket.on('submitAnswer', ({ roomId, questionId, selectedOption }) => {
        if (!rooms[roomId]) return;

        if (!rooms[roomId].answers[questionId]) {
            rooms[roomId].answers[questionId] = {};
        }

        rooms[roomId].answers[questionId][selectedOption] =
            (rooms[roomId].answers[questionId][selectedOption] || 0) + 1;

        rooms[roomId].answeredUsers.add(socket.id);

        io.to(roomId).emit('roomUpdate', {
            answeredCount: rooms[roomId].answeredUsers.size,
            totalUsers: rooms[roomId].totalUsers,
            currentQuestionIndex: rooms[roomId].currentQuestionIndex,
        });
    });


       // Handle the submitAssessment event
       socket.on('submitAssessment', ({ roomId, username }) => {
        console.log(`${username} submitted the assessment in room ${roomId}`);
        // Broadcast to all users in the room
        io.to(roomId).emit('assessmentSubmitted', {
            message: 'Assessment Submitted! All participants can now return to the dashboard.',
        });
    });



    // Handle next question
    socket.on('nextQuestion', ({ roomId }) => {
        if (!rooms[roomId]) return;

        rooms[roomId].answeredUsers.clear();
        rooms[roomId].currentQuestionIndex++;
        io.to(roomId).emit('roomUpdate', {
            answeredCount: 0,
            totalUsers: rooms[roomId].totalUsers,
            currentQuestionIndex: rooms[roomId].currentQuestionIndex,
        });
    });

    // Fetch answers
    socket.on('fetchAnswers', ({ roomId }) => {
        if (!rooms[roomId]) return;
        io.to(roomId).emit('answerData', rooms[roomId].answers);
    });

    // Handle disconnect
    socket.on('disconnect', () => {
        for (const roomId in rooms) {
            if (rooms[roomId].users[socket.id]) {
                delete rooms[roomId].users[socket.id];
                rooms[roomId].totalUsers = Object.keys(rooms[roomId].users).length;

                rooms[roomId].answeredUsers.delete(socket.id);

                io.to(roomId).emit('roomUpdate', {
                    answeredCount: rooms[roomId].answeredUsers.size,
                    totalUsers: rooms[roomId].totalUsers,
                    currentQuestionIndex: rooms[roomId].currentQuestionIndex,
                });
            }
        }
    });
});
