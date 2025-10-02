import React from 'react'
import { Table, Button, Space, Card, message } from 'antd'
import { PlusOutlined, LogoutOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { tasksAPI } from '../services/api'
import { auth } from '../utils/auth'
import CreateTaskModal from './CreateTaskModal'

const TaskList = () => {
    const navigate = useNavigate()
    const [tasks, setTasks] = React.useState([])
    const [loading, setLoading] = React.useState(false)
    const [pagination, setPagination] = React.useState({
        current: 1,
        pageSize: 10,
        total: 0,
    })
    const [sortField, setSortField] = React.useState('created_at')
    const [sortOrder, setSortOrder] = React.useState('descend')
    const [modalVisible, setModalVisible] = React.useState(false)

    const fetchTasks = async (page = pagination.current, pageSize = pagination.pageSize) => {
        setLoading(true)
        try {
            const params = {
                page,
                per_page: pageSize,
                sort_field: sortField,
                sort_order: sortOrder === 'ascend' ? 'asc' : 'desc',
            }

            const response = await tasksAPI.getTasks(params)
            const { data, total, current_page, per_page } = response.data

            setTasks(data)
            setPagination({
                current: current_page,
                pageSize: per_page,
                total: total,
            })
        } catch (error) {
            message.error('Ошибка загрузки задач')
        } finally {
            setLoading(false)
        }
    }

    React.useEffect(() => {
        fetchTasks()
    }, [sortField, sortOrder])

    const handleTableChange = (newPagination, filters, sorter) => {
        if (sorter.field !== sortField || sorter.order !== sortOrder) {
            setSortField(sorter.field)
            setSortOrder(sorter.order)
        }
        fetchTasks(newPagination.current, newPagination.pageSize)
    }

    const handleLogout = () => {
        auth.removeToken()
        navigate('/login')
        message.success('Вы успешно вышли из системы')
    }

    const handleTaskCreated = () => {
        fetchTasks(pagination.current, pagination.pageSize)
    }

    const columns = [
        {
            title: 'ID',
            dataIndex: 'id',
            key: 'id',
            sorter: true,
            width: 80,
        },
        {
            title: 'Название',
            dataIndex: 'title',
            key: 'title',
            sorter: true,
        },
        {
            title: 'Описание',
            dataIndex: 'description',
            key: 'description',
            ellipsis: true,
        },
        {
            title: 'Статус',
            dataIndex: 'status',
            key: 'status',
            sorter: true,
            render: (status) => {
                const statusMap = {
                    pending: 'В ожидании',
                    in_progress: 'В работе',
                    completed: 'Завершена',
                }
                return statusMap[status] || status
            },
        },
        {
            title: 'Пользователь',
            dataIndex: 'user_id',
            key: 'user_id',
            sorter: true,
        },
        {
            title: 'Создана',
            dataIndex: 'created_at',
            key: 'created_at',
            sorter: true,
            render: (date) => new Date(date).toLocaleString('ru-RU'),
        },
        {
            title: 'Обновлена',
            dataIndex: 'updated_at',
            key: 'updated_at',
            sorter: true,
            render: (date) => new Date(date).toLocaleString('ru-RU'),
        },
    ]

    return (
        <div className="container">
            <Card>
                <div className="header">
                    <h1>Список задач</h1>
                    <Space>
                        <Button
                            type="primary"
                            icon={<PlusOutlined />}
                            onClick={() => setModalVisible(true)}
                        >
                            Создать задачу
                        </Button>
                        <Button
                            icon={<LogoutOutlined />}
                            onClick={handleLogout}
                        >
                            Выйти
                        </Button>
                    </Space>
                </div>

                <Table
                    columns={columns}
                    dataSource={tasks}
                    rowKey="id"
                    pagination={pagination}
                    loading={loading}
                    onChange={handleTableChange}
                    scroll={{ x: 800 }}
                />

                <CreateTaskModal
                    visible={modalVisible}
                    onClose={() => setModalVisible(false)}
                    onTaskCreated={handleTaskCreated}
                />
            </Card>
        </div>
    )
}

export default TaskList