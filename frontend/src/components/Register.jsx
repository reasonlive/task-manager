import React from 'react'
import { Form, Input, Button, Card, message } from 'antd'
import { UserOutlined, LockOutlined, MailOutlined } from '@ant-design/icons'
import { Link, useNavigate } from 'react-router-dom'
import { authAPI } from '../services/api'

const Register = () => {
    const navigate = useNavigate()
    const [loading, setLoading] = React.useState(false)

    const onFinish = async (values) => {
        setLoading(true)
        try {
            await authAPI.register(values)
            message.success('Регистрация успешна! Теперь вы можете войти.')
            navigate('/login')
        } catch (error) {
            message.error(error.response?.data?.message || 'Ошибка регистрации')
        } finally {
            setLoading(false)
        }
    }

    return (
        <div className="login-container">
            <Card
                title="Регистрация"
                className="login-form"
                extra={<Link to="/login">Войти</Link>}
            >
                <Form
                    name="register"
                    onFinish={onFinish}
                    autoComplete="off"
                    size="large"
                >
                    <Form.Item
                        name="name"
                        rules={[{ required: true, message: 'Пожалуйста, введите имя!' }]}
                    >
                        <Input
                            prefix={<UserOutlined />}
                            placeholder="Имя"
                        />
                    </Form.Item>

                    <Form.Item
                        name="email"
                        rules={[
                            { required: true, message: 'Пожалуйста, введите email!' },
                            { type: 'email', message: 'Введите корректный email!' }
                        ]}
                    >
                        <Input
                            prefix={<MailOutlined />}
                            placeholder="Email"
                        />
                    </Form.Item>

                    <Form.Item
                        name="password"
                        rules={[
                            { required: true, message: 'Пожалуйста, введите пароль!' },
                            { min: 6, message: 'Пароль должен содержать минимум 6 символов!' }
                        ]}
                    >
                        <Input.Password
                            prefix={<LockOutlined />}
                            placeholder="Пароль"
                        />
                    </Form.Item>

                    <Form.Item
                        name="password_confirmation"
                        dependencies={['password']}
                        rules={[
                            { required: true, message: 'Пожалуйста, подтвердите пароль!' },
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    if (!value || getFieldValue('password') === value) {
                                        return Promise.resolve()
                                    }
                                    return Promise.reject(new Error('Пароли не совпадают!'))
                                },
                            }),
                        ]}
                    >
                        <Input.Password
                            prefix={<LockOutlined />}
                            placeholder="Подтверждение пароля"
                        />
                    </Form.Item>

                    <Form.Item>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={loading}
                            block
                        >
                            Зарегистрироваться
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </div>
    )
}

export default Register