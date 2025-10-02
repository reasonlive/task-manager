import React from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { auth } from './utils/auth'
import Login from './components/Login'
import Register from './components/Register'
import TaskList from './components/TaskList'

const ProtectedRoute = ({ children }) => {
    return auth.isAuthenticated() ? children : <Navigate to="/login" />
}

const PublicRoute = ({ children }) => {
    return !auth.isAuthenticated() ? children : <Navigate to="/tasks" />
}

const App = () => {
    return (
        <Routes>
            <Route
                path="/login"
                element={
                    <PublicRoute>
                        <Login />
                    </PublicRoute>
                }
            />
            <Route
                path="/register"
                element={
                    <PublicRoute>
                        <Register />
                    </PublicRoute>
                }
            />
            <Route
                path="/tasks"
                element={
                    <ProtectedRoute>
                        <TaskList />
                    </ProtectedRoute>
                }
            />
            <Route path="/" element={<Navigate to="/tasks" />} />
            <Route path="*" element={<Navigate to="/tasks" />} />
        </Routes>
    )
}

export default App