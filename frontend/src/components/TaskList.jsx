import React from 'react'
import { Table, Button, Space, Card, message, Input, Select } from 'antd'
import { PlusOutlined, LogoutOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { tasksAPI } from '../services/api'
import { auth } from '../utils/auth'
import CreateTaskModal from './CreateTaskModal.jsx'

const { Search } = Input
const { Option } = Select

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
    const [filters, setFilters] = React.useState({
        title: '',
        status: '',
        user_id: '',
    })

    const fetchTasks = async (page = pagination.current, pageSize = pagination.pageSize) => {
        setLoading(true)
        try {
            const params = {
                page,
                per_page: pageSize,
                sort_field: sortField,
                sort_order: sortOrder === 'ascend' ? 'asc' : 'desc',
            }

            // Добавляем фильтры в параметры запроса, если они заданы
            if (filters.title) {
                params.title = filters.title
            }
            if (filters.status) {
                params.status = filters.status
            }
            if (filters.user_id) {
                params.user_id = filters.user_id
            }

            const response = await tasksAPI.getTasks(params)
            const { data, total, pagination } = response.data

            setTasks(data)
            setPagination({
                current: pagination.current_page,
                pageSize: pagination.per_page,
                total: pagination.total_items,
            })
        } catch (error) {
            message.error('Ошибка загрузки задач')
        } finally {
            setLoading(false)
        }
    }

    React.useEffect(() => {
        fetchTasks(1) // Сбрасываем на первую страницу при изменении фильтров или сортировки
    }, [sortField, sortOrder, filters])

    const handleTableChange = (newPagination, filters, sorter) => {
        if (sorter.field !== sortField || sorter.order !== sortOrder) {
            setSortField(sorter.field)
            setSortOrder(sorter.order)
        } else {
            fetchTasks(newPagination.current, newPagination.pageSize)
        }
    }

    const handleFilterChange = (field, value) => {
        setFilters(prev => ({
            ...prev,
            [field]: value
        }))
    }

    const handleSearch = (value) => {
        handleFilterChange('title', value)
    }

    const handleStatusChange = (value) => {
        handleFilterChange('status', value)
    }

    const handleUserIdChange = (e) => {
        handleFilterChange('user_id', e.target.value)
    }

    const clearFilters = () => {
        setFilters({
            title: '',
            status: '',
            user_id: '',
        })
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
            dataIndex: 'user_name',
            key: 'user_name',
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

                {/* Фильтры */}
                <div style={{ marginBottom: 16 }}>
                    <Space wrap>
                        <Search
                            placeholder="Поиск по названию"
                            allowClear
                            onSearch={handleSearch}
                            style={{ width: 200 }}
                            defaultValue={filters.title}
                        />

                        <Select
                            placeholder="Статус"
                            allowClear
                            style={{ width: 150 }}
                            onChange={handleStatusChange}
                            value={filters.status || undefined}
                        >
                            <Option value="pending">В ожидании</Option>
                            <Option value="in_progress">В работе</Option>
                            <Option value="completed">Завершена</Option>
                        </Select>

                        <Search
                            placeholder="ID пользователя"
                            allowClear
                            onSearch={(value) => handleFilterChange('user_id', value)}
                            style={{ width: 150 }}
                            defaultValue={filters.user_id}
                        />

                        <Button onClick={clearFilters}>
                            Сбросить фильтры
                        </Button>
                    </Space>
                </div>

                <Table
                    columns={columns}
                    dataSource={tasks}
                    rowKey="id"
                    pagination={{
                        current: pagination.current,
                        pageSize: pagination.pageSize,
                        total: pagination.total,
                        showSizeChanger: true,
                        showQuickJumper: true,
                        showTotal: (total, range) =>
                            `Задачи ${range[0]}-${range[1]} из ${total}`,
                        pageSizeOptions: ['10', '20', '50', '100'],
                    }}
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