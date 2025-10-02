import React from 'react'
import { Form, Input, Button, Card, message } from 'antd'
import { UserOutlined, LockOutlined } from '@ant-design/icons'
import { Link, useNavigate } from 'react-router-dom'
import { authAPI } from '../services/api'
import { auth } from '../utils/auth'

const Login = () => {
    const navigate = useNavigate()
    const [loading, setLoading] = React.useState(false)

    const onFinish = async (values) => {
        setLoading(true)
        try {
            const response = await authAPI.login(values)
            console.log(response);
            const { token } = response.data

            auth.setToken(token)
            message.success('Успешный вход!')
            navigate('/tasks')
        } catch (error) {
            message.error(error.response?.data?.message || 'Ошибка входа')
        } finally {
            setLoading(false)
        }
    }

    // Если пользователь уже авторизован, перенаправляем на страницу задач
    React.useEffect(() => {
        if (auth.isAuthenticated()) {
            navigate('/tasks')
        }
    }, [navigate])

    return (
        <div className="login-container">
            <Card
                title="Вход в систему"
                className="login-form"
                extra={<Link to="/register">Регистрация</Link>}
            >
                <Form
                    name="login"
                    onFinish={onFinish}
                    autoComplete="off"
                    size="large"
                >
                    <Form.Item
                        name="email"
                        rules={[
                            { required: true, message: 'Пожалуйста, введите email!' },
                            { type: 'email', message: 'Введите корректный email!' }
                        ]}
                    >
                        <Input
                            prefix={<UserOutlined />}
                            placeholder="Email"
                        />
                    </Form.Item>

                    <Form.Item
                        name="password"
                        rules={[{ required: true, message: 'Пожалуйста, введите пароль!' }]}
                    >
                        <Input.Password
                            prefix={<LockOutlined />}
                            placeholder="Пароль"
                        />
                    </Form.Item>

                    <Form.Item>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={loading}
                            block
                        >
                            Войти
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </div>
    )
}

export default Login