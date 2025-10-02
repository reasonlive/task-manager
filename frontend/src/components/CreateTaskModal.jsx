import React from 'react'
import { Modal, Form, Input, Button, message } from 'antd'
import { tasksAPI } from '../services/api'

const { TextArea } = Input

const CreateTaskModal = ({ visible, onClose, onTaskCreated }) => {
    const [form] = Form.useForm()
    const [loading, setLoading] = React.useState(false)

    const handleOk = async () => {
        try {
            const values = await form.validateFields()
            setLoading(true)

            await tasksAPI.createTask(values)
            message.success('Задача успешно создана!')
            form.resetFields()
            onTaskCreated()
            onClose()
        } catch (error) {
            if (error.errorFields) {
                message.error('Пожалуйста, заполните все обязательные поля')
            } else {
                message.error(error.response?.data?.message || 'Ошибка создания задачи')
            }
        } finally {
            setLoading(false)
        }
    }

    const handleCancel = () => {
        form.resetFields()
        onClose()
    }

    return (
        <Modal
            title="Создать новую задачу"
            open={visible}
            onOk={handleOk}
            onCancel={handleCancel}
            confirmLoading={loading}
            footer={[
                <Button key="cancel" onClick={handleCancel}>
                    Отмена
                </Button>,
                <Button
                    key="submit"
                    type="primary"
                    loading={loading}
                    onClick={handleOk}
                >
                    Создать
                </Button>,
            ]}
        >
            <Form
                form={form}
                layout="vertical"
                name="createTask"
            >
                <Form.Item
                    name="title"
                    label="Название задачи"
                    rules={[{ required: true, message: 'Пожалуйста, введите название задачи!' }]}
                >
                    <Input placeholder="Введите название задачи" />
                </Form.Item>

                <Form.Item
                    name="description"
                    label="Описание задачи"
                    rules={[{ required: true, message: 'Пожалуйста, введите описание задачи!' }]}
                >
                    <TextArea
                        rows={4}
                        placeholder="Введите описание задачи"
                    />
                </Form.Item>
            </Form>
        </Modal>
    )
}

export default CreateTaskModal